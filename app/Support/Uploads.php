<?php

namespace App\Support;

/** Upload limits from config/uploads.php, in the shape the browser check (window.checkUpload) uses. */
class Uploads
{
    public static function forJs(string $kind): array
    {
        $rule = config("uploads.$kind");
        $types = strtoupper(implode(', ', $rule['extensions']));
        $mb = rtrim(rtrim(number_format($rule['max_kb'] / 1024, 1, '.', ''), '0'), '.');

        return [
            'maxKb'      => $rule['max_kb'],
            'extensions' => $rule['extensions'],
            'typeError'  => __('upload.type_error', ['types' => $types]),
            'sizeError'  => __('upload.size_error', ['mb' => $mb]),
            'emptyError' => __('upload.empty_error'),
            'hint'       => __('upload.hint', ['types' => $types, 'mb' => $mb]),
        ];
    }
}
