<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Честный ЗНАК label icon gets its image: the square sign of the owner's cz-logo-new.svg, monochrome
 * (black square, white mark — thermal printers print one colour), public/images/marks/chestny_znak.png.
 * Only touches the row while it has no image or the built-in one: a file the super admin uploaded is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $file = public_path('images/marks/chestny_znak.png');
        $size = is_file($file) ? @getimagesize($file) : false;
        if (!$size) {
            return;
        }

        DB::table('label_marks')->where('key', 'chestny_znak')
            ->where(fn ($q) => $q->whereNull('path')->orWhere('path', 'images/marks/chestny_znak.png'))->update([
            'path' => 'images/marks/chestny_znak.png', 'width' => $size[0], 'height' => $size[1],
            'source' => 'Товарный знак ООО «Оператор-ЦРПТ» (св. 690729, 717580). Файл предоставил владелец Markor (cz-logo-new.svg: только знак, монохромный). '
                . 'Использование разрешено участникам оборота маркированных товаров, на территории РФ (честныйзнак.рф/logo.consent).',
            'updated_at' => now(),
        ]);
        Cache::forget('label_marks.v1');
    }

    public function down(): void
    {
        DB::table('label_marks')->where('key', 'chestny_znak')->where('path', 'images/marks/chestny_znak.png')
            ->update(['path' => null, 'width' => null, 'height' => null, 'updated_at' => now()]);
        Cache::forget('label_marks.v1');
    }
};
