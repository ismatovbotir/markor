<?php

namespace App\Domain\System;

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Health of the background machinery for the super admin:
 *  - scheduler: ticks every minute (cron `php artisan schedule:run`) — imports CSV batches, Asl Belgisi fallbacks;
 *  - queue worker (`php artisan queue:work`): PDFs to Telegram, PDF archive, Asl Belgisi checks;
 *    a QueueHeartbeat job is queued every minute — when a worker runs it, the worker is alive.
 */
class SystemStatus
{
    public const SCHEDULER_KEY = 'system.scheduler_heartbeat';
    public const QUEUE_KEY = 'system.queue_heartbeat';
    public const STALE_MINUTES = 3;

    /** Called by the scheduler every minute. */
    public static function tick(): void
    {
        Cache::forever(self::SCHEDULER_KEY, now()->toIso8601String());
        if (config('queue.default') !== 'sync') {
            // one heartbeat waiting at a time: no pile-up when no worker runs
            if (!self::pendingHeartbeat()) {
                QueueHeartbeat::dispatch();
            }
        } else {
            Cache::forever(self::QUEUE_KEY, now()->toIso8601String()); // sync driver: jobs run in the request itself
        }
    }

    private static function pendingHeartbeat(): bool
    {
        return config('queue.default') === 'database' && Schema::hasTable('jobs')
            && DB::table('jobs')->where('payload', 'like', '%QueueHeartbeat%')->exists();
    }

    public function snapshot(): array
    {
        $scheduler = self::time(self::SCHEDULER_KEY);
        $worker = self::time(self::QUEUE_KEY);
        $db = config('queue.default') === 'database' && Schema::hasTable('jobs');
        $pending = $db ? DB::table('jobs')->where('payload', 'not like', '%QueueHeartbeat%')->count() : null;
        $oldest = $db ? DB::table('jobs')->where('payload', 'not like', '%QueueHeartbeat%')->min('created_at') : null;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->orderByDesc('id')->limit(10)->get(['id', 'uuid', 'payload', 'exception', 'failed_at']) : collect();

        return [
            'driver'       => config('queue.default'),
            'scheduler'    => ['at' => $scheduler, 'ok' => self::fresh($scheduler)],
            'worker'       => ['at' => $worker, 'ok' => self::fresh($worker)],
            'pending'      => $pending,
            'oldest'       => $oldest ? Carbon::createFromTimestamp((int) $oldest) : null,
            'failed_count' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            'failed'       => $failed->map(fn ($f) => [
                'id' => $f->id, 'uuid' => $f->uuid, 'failed_at' => Carbon::parse($f->failed_at),
                'job' => class_basename((string) (json_decode($f->payload, true)['displayName'] ?? '?')),
                // first line only — never dump payloads (they can hold chat ids / file ids)
                'error' => self::mask(\Illuminate\Support\Str::limit(preg_replace('# in /\S+$#', '', (string) strtok((string) $f->exception, "\n")), 240)),
            ])->all(),
        ];
    }

    /** Something needs attention (red badge in the admin menu). */
    /** One failed job in full for the details window: whole error with the start of the trace, tokens masked. */
    public function failedDetail(string $uuid): ?array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return null;
        }
        $f = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        if (!$f) {
            return null;
        }
        $payload = json_decode((string) $f->payload, true) ?: [];
        $lines = preg_split('/\R/', (string) $f->exception);

        return [
            'uuid' => $f->uuid, 'queue' => $f->queue, 'connection' => $f->connection, 'failed_at' => Carbon::parse($f->failed_at),
            'job' => (string) ($payload['displayName'] ?? '?'), 'attempts' => $payload['attempts'] ?? null,
            'max_tries' => $payload['maxTries'] ?? null, 'timeout' => $payload['timeout'] ?? null,
            'error' => self::mask(implode("\n", array_slice($lines, 0, 60))) . (count($lines) > 60 ? "\n… (" . (count($lines) - 60) . ' more lines)' : ''),
        ];
    }

    /** Never show a bot token (or the configured one) — failed-job traces can contain request URLs. */
    public static function mask(string $text): string
    {
        $text = preg_replace('#\b\d{6,12}:[A-Za-z0-9_-]{30,}\b#', '***:***', $text);
        $token = (string) config('telegram.token');

        return $token !== '' ? str_replace($token, '***:***', $text) : $text;
    }

    public function problems(): int
    {
        $s = $this->snapshot();

        return (int) !$s['scheduler']['ok'] + (int) !$s['worker']['ok'] + (int) ($s['failed_count'] > 0);
    }

    private static function time(string $key): ?Carbon
    {
        $v = Cache::get($key);

        return $v ? Carbon::parse($v) : null;
    }

    private static function fresh(?Carbon $at): bool
    {
        return $at !== null && $at->gt(now()->subMinutes(self::STALE_MINUTES));
    }
}
