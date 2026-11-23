<?php

namespace App\Domain\Marking\Support;

/**
 * National marking systems whose GS1 DataMatrix codes Markor prints. Codes of all of them look
 * alike (CRPT technology), so a category's structure alone does not always tell the system apart.
 * A new country = a constant here + its labels in lang/*.json (`marking_system.<key>`) + categories.
 */
final class MarkingSystem
{
    public const ASLBELGISI = 'aslbelgisi';     // Uzbekistan, CRPT Turon
    public const CHESTNY_ZNAK = 'chestny_znak'; // Russia, CRPT

    public const ALL = [self::ASLBELGISI, self::CHESTNY_ZNAK];

    public static function label(?string $system): string
    {
        return __('marking_system.' . ($system ?: self::ASLBELGISI));
    }

    /** [key => label] for selects. */
    public static function options(): array
    {
        return collect(self::ALL)->mapWithKeys(fn ($s) => [$s => self::label($s)])->all();
    }
}
