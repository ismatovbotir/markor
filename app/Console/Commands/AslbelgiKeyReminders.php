<?php

namespace App\Console\Commands;

use App\Domain\Companies\Company;
use App\Domain\Telegram\Notifier;
use Illuminate\Console\Command;

/**
 * Daily: tells the company owner in Telegram that the Asl Belgisi API key expires — 14 days before,
 * 3 days before and on expiry. Each threshold is sent once per key (companies.aslbelgi_key_reminded =
 * "<expiry date>:<threshold>"; a new key has a new expiry date, so its reminders start again).
 * If a day was missed, only the most urgent threshold due is sent.
 */
class AslbelgiKeyReminders extends Command
{
    public const LEVELS = ['d14' => 1, 'd3' => 2, 'expired' => 3];

    protected $signature = 'aslbelgi:key-reminders';
    protected $description = 'Telegram reminders to company owners before their Asl Belgisi API key expires';

    public function handle(Notifier $notifier): int
    {
        $sent = 0;
        Company::whereNotNull('aslbelgi_api_key')->whereNotNull('aslbelgi_api_key_expires_at')
            ->where('aslbelgi_api_key_expires_at', '<=', now()->addDays(15))
            ->get()->each(function (Company $company) use ($notifier, &$sent) {
                $expires = $company->aslbelgi_api_key_expires_at->copy()->startOfDay();
                $days = (int) now()->startOfDay()->diffInDays($expires, false);
                $threshold = self::threshold($days);
                if (!$threshold) {
                    return;
                }
                [$lastDate, $lastThreshold] = array_pad(explode(':', (string) $company->aslbelgi_key_reminded, 2), 2, null);
                if ($lastDate === $expires->toDateString() && (self::LEVELS[$lastThreshold] ?? 0) >= self::LEVELS[$threshold]) {
                    return; // this threshold (or a later one) was already sent for this key
                }
                if ($notifier->aslbelgiKeyReminder($company, $threshold, max(0, $days))) {
                    $company->forceFill(['aslbelgi_key_reminded' => $expires->toDateString() . ':' . $threshold])->save();
                    $sent++;
                }
            });
        $this->line("Sent $sent reminder(s)");

        return self::SUCCESS;
    }

    public static function threshold(int $days): ?string
    {
        return match (true) {
            $days <= 0  => 'expired',
            $days <= 3  => 'd3',
            $days <= 14 => 'd14',
            default     => null,
        };
    }
}
