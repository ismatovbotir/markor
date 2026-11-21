<?php

namespace App\Domain\Billing;

use App\Domain\Companies\Company;

/**
 * Price per loaded DataMatrix code and the discount of a company.
 * During the company's free period (`free_until`, inclusive) loading is free (100%). After it, the
 * company's own discount applies until `discount_until`; after that, or without one, the default
 * discount from config/billing.php applies.
 */
class Billing
{
    public static function unitPrice(): float
    {
        return (float) config('billing.price_per_code');
    }

    /** Is the company still in its free period? */
    public static function isFree(?Company $company): bool
    {
        return (bool) $company?->free_until?->copy()->endOfDay()->isFuture();
    }

    /** First day the company pays (null = no free period set). */
    public static function paidFrom(?Company $company): ?\Illuminate\Support\Carbon
    {
        return $company?->free_until?->copy()->addDay();
    }

    public static function discountFor(?Company $company): float
    {
        if (self::isFree($company)) {
            return 100.0;
        }

        $own = $company?->discount_percent !== null
            && ($company->discount_until === null || $company->discount_until->endOfDay()->isFuture());

        $percent = $own ? $company->discount_percent : config('billing.discount_percent');

        return min(100, max(0, (float) $percent));
    }

    /** Until when the current discount runs: the free period, else the own discount's end (null = no end). */
    public static function discountUntil(?Company $company): ?\Illuminate\Support\Carbon
    {
        if (self::isFree($company)) {
            return $company->free_until;
        }

        return $company?->discount_percent !== null ? $company->discount_until : null;
    }

    /** Set when a company registers: free until today + N months. */
    public static function freePeriod(): array
    {
        $months = (int) config('billing.free_months');

        return $months > 0 ? ['free_until' => now()->addMonthsNoOverflow($months)->toDateString()] : [];
    }

    /** Amount to pay for `$codes` codes after the discount, rounded to whole so'm. */
    public static function amount(int $codes, float $unitPrice, float $discountPercent): float
    {
        return round($codes * $unitPrice * (1 - $discountPercent / 100));
    }

    public static function money(float|int|null $value): string
    {
        return number_format((float) $value, 0, '.', ' ') . ' ' . config('billing.currency');
    }

    public static function percent(float|int|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
