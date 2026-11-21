<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks what is really inside an uploaded table file, by its first bytes:
 *  - .csv / .txt must be text: no NUL bytes, almost no control characters (the GS / RS separators
 *    inside marking codes, tabs and line breaks are allowed);
 *  - .xlsx / .ods must be a ZIP package (that is what these formats are).
 * Laravel's `mimes` rule is not used for code files: the system type detector reports operator
 * CSVs as "application/octet-stream" because of the GS characters, and would reject real files.
 */
class PlainTextFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ext = strtolower(method_exists($value, 'getClientOriginalExtension') ? $value->getClientOriginalExtension() : '');
        $head = @file_get_contents($value->getRealPath(), false, null, 0, 8192);
        if ($head === false) {
            $fail(__('upload.not_text'));
            return;
        }
        if (in_array($ext, ['xlsx', 'ods'], true)) {
            if (!str_starts_with($head, "PK\x03\x04")) {
                $fail(__('upload.not_spreadsheet'));
            }
            return;
        }
        if (!self::isText($head)) {
            $fail(__('upload.not_text'));
        }
    }

    public static function isText(string $bytes): bool
    {
        if ($bytes === '' || str_contains($bytes, "\0")) {
            return $bytes !== '' ? false : true;
        }
        $control = preg_match_all('/[\x01-\x08\x0B\x0C\x0E-\x1C\x7F]/', $bytes);

        return $control / strlen($bytes) < 0.01;
    }
}
