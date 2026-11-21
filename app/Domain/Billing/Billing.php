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

        return self::paidDiscountFor($company);
    }

    /** The discount that applies to PAID codes (own discount while valid, otherwise the default). */
    public static function paidDiscountFor(?Company $company): float
    {

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

    /** Free codes left in the current free period (null = no cap). */
    public static function freeCodesLeft(?Company $company): ?int
    {
        $limit = (int) config('billing.free_codes_limit');

        return $limit > 0 ? max(0, $limit - (int) ($company?->free_codes_used ?? 0)) : null;
    }

    /**
     * Price of a batch of $codes for a company, fixed at load time. In the free period the first codes
     * up to the cap are free and the rest are charged normally. Reserves the free codes on the company
     * (call inside a transaction with the company row locked).
     * @return array{unit_price: float, discount_percent: float, amount: float, free_codes: int}
     */
    public static function priceBatch(Company $company, int $codes): array
    {
        $price = self::unitPrice();
        $free = 0;
        if (self::isFree($company)) {
            $left = self::freeCodesLeft($company);
            $free = $left === null ? $codes : min($codes, $left);
            if ($free > 0 && $left !== null) {
                $company->increment('free_codes_used', $free);
            }
        }
        $amount = self::amount($codes - $free, $price, self::paidDiscountFor($company));
        $full = $codes * $price;

        return [
            'unit_price'       => $price,
            'discount_percent' => $full > 0 ? round((1 - $amount / $full) * 100, 2) : 0.0, // blended: free part + paid part
            'amount'           => $amount,
            'free_codes'       => $free,
        ];
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
        // rounded UP to whole so'm (a 1-code file must not round down to free); 100% discount stays 0
        return (float) ceil(round($codes * $unitPrice * (1 - $discountPercent / 100), 6));
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
