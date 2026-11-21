<?php

namespace Database\Seeders;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Loads "Biz taklif qilamiz" items from database/seeders/data/offers.csv (Excel-friendly: ; separated, UTF-8).
 * Pictures go to database/seeders/data/offer-images/ and are referenced by file name in the `image` column.
 *
 *   php artisan db:seed --class=OfferItemsSeeder
 *
 * Rows are matched by `slug`: running again updates them. Items that are not in the file are left alone.
 */
class OfferItemsSeeder extends Seeder
{
    private const COLUMNS = ['category', 'slug', 'title_uz', 'title_ru', 'title_zh', 'description_uz', 'description_ru',
        'description_zh', 'price', 'url', 'image', 'button', 'order', 'visible'];

    public function run(): void
    {
        $path = database_path('seeders/data/offers.csv');
        $handle = fopen($path, 'r');
        $header = null;
        $line = 0;
        $count = 0;

        while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
            $line++;
            if ($header === null) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]); // Excel BOM
                $header = array_map('trim', $row);
                $missing = array_diff(self::COLUMNS, $header);
                if ($missing) {
                    throw new \RuntimeException('offers.csv: missing columns: ' . implode(', ', $missing));
                }
                continue;
            }
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $r = array_map(fn ($v) => trim((string) $v), array_combine($header, array_pad($row, count($header), '')));
            $category = OfferCategory::where('slug', $r['category'])->where('kind', 'items')->first();
            if (!$category || $r['slug'] === '' || $r['title_uz'] === '') {
                $this->command?->warn("offers.csv line $line skipped: unknown category '{$r['category']}' or empty slug/title_uz");
                continue;
            }

            $values = [
                'offer_category_id' => $category->id, 'category' => $category->slug, 'icon' => $category->icon,
                'title_uz' => $r['title_uz'], 'title_ru' => $r['title_ru'] ?: null, 'title_zh' => $r['title_zh'] ?: null,
                'description_uz' => $r['description_uz'] ?: null, 'description_ru' => $r['description_ru'] ?: null, 'description_zh' => $r['description_zh'] ?: null,
                'price' => $r['price'] ?: null, 'url' => $r['url'] ?: null,
                'action' => in_array($r['button'], ['order', 'call'], true) ? $r['button'] : 'order',
                'sort_order' => (int) $r['order'],
                'is_active' => !in_array(mb_strtolower($r['visible']), ['0', 'no', "yo'q", 'нет', 'false'], true),
            ];

            if ($r['image'] !== '') {
                $source = database_path('seeders/data/offer-images/' . basename($r['image']));
                if (is_file($source)) {
                    $values['image_path'] = 'offers/' . $r['slug'] . '.' . strtolower(pathinfo($source, PATHINFO_EXTENSION));
                    Storage::disk('public')->put($values['image_path'], file_get_contents($source));
                } else {
                    $this->command?->warn("offers.csv line $line: image not found: {$r['image']}");
                }
            }

            Offer::updateOrCreate(['slug' => $r['slug']], $values);
            $count++;
        }
        fclose($handle);

        $this->command?->info("Offer items loaded: $count");
    }
}
