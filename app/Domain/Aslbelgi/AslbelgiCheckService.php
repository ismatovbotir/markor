<?php

namespace App\Domain\Aslbelgi;

use App\Domain\Billing\BalanceService;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Jobs\RunAslbelgiCheck;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * "Asl Belgisi tekshiruvi" of a CSV batch (services A + B, read-only API):
 * every code is looked up with POST cod/private/codes (status, current owner). Codes with a problem
 * (config aslbelgi.problem_statuses, not ours, unknown, invalid) get `aslbelgi_problem` and are not
 * printed; the rest of the batch prints normally.
 *
 * Money: charged in full when ordered (10 so'm/code, no discount); when it finishes, only the codes the
 * API actually answered for are paid, the rest is refunded (API down / key invalid → everything back).
 */
class AslbelgiCheckService
{
    public function __construct(private BalanceService $balance) {}

    public static function unitPrice(): float
    {
        return (float) config('billing.aslbelgi_check_unit_price', 10);
    }

    /** Codes that an order for this batch covers (all codes of the batch, problem ones included). */
    public static function codesOf(ImportBatch $batch): int
    {
        return MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->count();
    }

    /** Is the company's key usable for a check (entered and not expired)? */
    public static function keyUsable(?Company $company): bool
    {
        return (bool) $company?->hasAslbelgiKey()
            && !($company->aslbelgi_api_key_expires_at && $company->aslbelgi_api_key_expires_at->isPast());
    }

    /** Why this user cannot order a check for the batch (translation key), or null when they can. */
    public function blocker(ImportBatch $batch, ?User $user): ?string
    {
        $company = $batch->company;

        return match (true) {
            !$user || $user->isAdmin() || !$user->company_id || $user->company_id !== $batch->company_id => 'aslbelgi_check.not_allowed',
            !$company?->hasAslbelgiKey() => 'aslbelgi_check.no_key',
            !self::keyUsable($company) => 'aslbelgi_check.key_expired',
            $batch->status !== 'completed' || $batch->is_locked || self::codesOf($batch) === 0 => 'aslbelgi_check.not_ready',
            AslbelgiCheck::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->whereIn('status', AslbelgiCheck::ACTIVE)->exists() => 'aslbelgi_check.already_running',
            default => null,
        };
    }

    /**
     * Charges and queues a check.
     * @throws InsufficientBalance when the balance does not cover it (nothing is created)
     * @throws \DomainException with a translation key when the order is not allowed
     */
    public function order(ImportBatch $batch, User $user): AslbelgiCheck
    {
        if ($reason = $this->blocker($batch, $user)) {
            throw new \DomainException($reason);
        }
        $check = $this->balance->orderAslbelgiCheck($batch, $user, self::codesOf($batch), self::unitPrice());
        \App\Domain\Reporting\AuditLog::record('aslbelgi.check_ordered', 'import_batch', $batch->id, ['check' => $check->id, 'codes' => $check->codes, 'amount' => $check->amount]);
        RunAslbelgiCheck::dispatch($check->id);

        return $check;
    }

    /** Runs a queued check (safe to call twice: only one caller claims it). */
    public function run(int $checkId): void
    {
        $claimed = AslbelgiCheck::withoutGlobalScope('owner')->whereKey($checkId)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
        if (!$claimed) {
            return;
        }
        $check = AslbelgiCheck::withoutGlobalScope('owner')->with('company', 'batch')->findOrFail($checkId);

        $checked = 0;
        $error = null;
        try {
            $before = self::problemHash($check->import_batch_id);
            [$checked, $error] = $this->lookup($check);
            if ($before !== self::problemHash($check->import_batch_id)) {
                // the set of codes that may be printed changed: stored PDFs of this batch are stale now
                ImportBatch::withoutGlobalScope('owner')->whereKey($check->import_batch_id)->increment('aslbelgi_revision');
            }
        } catch (\Throwable $e) {
            report($e); // our code never puts the key into exception messages
            $error = 'internal';
        }

        $this->finish($check, $checked, $error);
    }

    /** Ends a check: final status, problem count, and the refund of codes that were not checked. */
    public function finish(AslbelgiCheck $check, int $checked, ?string $error): void
    {
        $problems = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $check->import_batch_id)->whereNotNull('aslbelgi_problem')->count();
        $status = $error === null ? 'done' : ($checked > 0 ? 'partial' : 'failed');

        $this->balance->settleAslbelgiCheck($check, [
            'status' => $status, 'checked' => $checked, 'problems' => $problems, 'error' => $error,
        ], $checked);
    }

    /** @return array{0: int, 1: ?string} codes checked by the API, error key (null = all done) */
    private function lookup(AslbelgiCheck $check): array
    {
        $company = $check->company;
        $client = self::keyUsable($company) ? $company->aslbelgiClient() : null;
        if (!$client) {
            return [0, 'key_invalid'];
        }

        $problemStatuses = (array) config('aslbelgi.problem_statuses', []);
        $checked = 0;
        $error = null;

        MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $check->import_batch_id)
            ->select(['id', 'code', 'gtin', 'serial'])
            ->chunkById(max(1, min(1000, (int) config('aslbelgi.check.chunk', 1000))), function ($codes) use ($check, $company, $client, $problemStatuses, &$checked, &$error) {
                $now = now();
                $idents = [];   // identification code → marking_codes.id
                $results = [];  // id => [status, problem]
                foreach ($codes as $code) {
                    $ident = self::identification($code);
                    if ($ident === null) {
                        $results[$code->id] = [null, 'invalid']; // never sent: the API rejects the whole request for one bad code
                    } else {
                        $idents[$ident] = $code->id;
                    }
                }

                if ($idents) {
                    $this->throttle($company->id);
                    $answer = $client->privateCodes(array_keys($idents));

                    if (!$answer['ok'] && $answer['reason'] !== 'not_ours') {
                        $error = $answer['reason'];
                        $this->store($results, $now); // the invalid ones are known anyway

                        return false; // stop: the key is invalid or the service is down; the rest stays unchecked
                    }

                    if (!$answer['ok']) {
                        // 307: the key has business access to none of these codes
                        foreach ($idents as $id) {
                            $results[$id] = [null, 'not_ours'];
                        }
                    } else {
                        $match = self::matcher($idents);
                        foreach ($answer['results'] as $item) {
                            $id = $match((string) data_get($item, 'codeData.code', ''));
                            if ($id === null) {
                                continue;
                            }
                            $status = data_get($item, 'codeData.status');
                            $status = is_string($status) ? mb_substr($status, 0, 40) : null;
                            $owner = data_get($item, 'turnoverData.ownerInfo.ownerTin');
                            $problem = (is_string($owner) && $owner !== '' && $company->tin && $owner !== $company->tin)
                                ? 'not_ours'
                                : ($problemStatuses[$status] ?? null);
                            $results[$id] = [$status, $problem];
                        }
                        foreach ($answer['forbidden'] as $forbidden) {
                            if (($id = $match($forbidden)) !== null) {
                                $results[$id] = [null, 'not_ours'];
                            }
                        }
                        foreach ($idents as $id) {
                            $results[$id] ??= [null, 'unknown']; // left out of the answer: not found in the registry
                        }
                    }
                    $checked += count($idents);
                }

                $this->store($results, $now);
                AslbelgiCheck::withoutGlobalScope('owner')->whereKey($check->id)->update(['checked' => $checked, 'updated_at' => now()]);

                return true;
            });

        return [$checked, $error];
    }

    /** Bulk update of the results of one chunk, one query per (status, problem) pair. */
    private function store(array $results, $now): void
    {
        $groups = [];
        foreach ($results as $id => [$status, $problem]) {
            $groups[($status ?? '') . '|' . ($problem ?? '')][] = $id;
        }
        foreach ($groups as $key => $ids) {
            [$status, $problem] = explode('|', $key, 2);
            foreach (array_chunk($ids, 500) as $part) {
                MarkingCode::withoutGlobalScope('owner')->whereIn('id', $part)->update([
                    'aslbelgi_status' => $status !== '' ? $status : null,
                    'aslbelgi_problem' => $problem !== '' ? $problem : null,
                    'aslbelgi_checked_at' => $now,
                ]);
            }
        }
    }

    /**
     * The identification code the API wants (01 + GTIN + 21 + serial, no verification part), or null when
     * the API would reject it (shorter than 20 characters, Cyrillic or other non-printable-ASCII characters).
     */
    public static function identification(MarkingCode $code): ?string
    {
        $ident = ($code->gtin && $code->serial !== null && $code->serial !== '')
            ? '01' . $code->gtin . '21' . $code->serial
            : (string) $code->code;

        return strlen($ident) >= 20 && preg_match('/^[\x21-\x7E]+$/', $ident) ? $ident : null;
    }

    /** Finds our code for an API code: exact, or ours as a prefix (the API may add the verification part). */
    private static function matcher(array $idents): \Closure
    {
        $lengths = array_unique(array_map('strlen', array_keys($idents)));
        rsort($lengths);

        return function (string $apiCode) use ($idents, $lengths): ?int {
            if (isset($idents[$apiCode])) {
                return $idents[$apiCode];
            }
            foreach ($lengths as $length) {
                if (strlen($apiCode) > $length && isset($idents[$prefix = substr($apiCode, 0, $length)])) {
                    return $idents[$prefix];
                }
            }

            return null;
        };
    }

    /** At most config('aslbelgi.check.per_second') requests a second per company (key). */
    private function throttle(int $companyId): void
    {
        $key = 'aslbelgi-codes:' . $companyId;
        $limit = max(1, (int) config('aslbelgi.check.per_second', 10));
        for ($i = 0; $i < 50 && !RateLimiter::attempt($key, $limit, fn () => true, 1); $i++) {
            Sleep::usleep(100_000);
        }
    }

    private static function problemHash(int $batchId): string
    {
        return md5(MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batchId)
            ->whereNotNull('aslbelgi_problem')->orderBy('id')->pluck('id')->implode(','));
    }

    /**
     * Fallback when no queue worker picked a check up, and a guard for checks that died while running:
     * queued > 2 min → run here; running > 30 min → finished with what was checked (rest refunded).
     */
    public function sweep(): int
    {
        $count = 0;
        AslbelgiCheck::withoutGlobalScope('owner')->where('status', 'queued')->where('created_at', '<', now()->subMinutes(2))
            ->orderBy('id')->limit(5)->pluck('id')->each(function ($id) use (&$count) {
                $this->run($id);
                $count++;
            });
        AslbelgiCheck::withoutGlobalScope('owner')->where('status', 'running')->where('updated_at', '<', now()->subMinutes(30))
            ->get()->each(function (AslbelgiCheck $check) use (&$count) {
                $this->finish($check, (int) $check->checked, 'timeout');
                $count++;
            });

        return $count;
    }
}
