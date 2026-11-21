<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\ProductGroup;
use Illuminate\Database\Seeder;

/**
 * Asl Belgisi product categories and the DataMatrix structure of their marking codes.
 * Safe for production: run again any time (`php artisan db:seed --class=ProductGroupSeeder --force`).
 *  - a missing category (by slug) is created;
 *  - an existing one keeps the owner's edits — only empty fields (names, structure, sample) are filled.
 *
 * VERIFIED structures (official spec or real client files) are active. Categories whose structure
 * is not confirmed yet are created INACTIVE without a structure: the parser ignores them until the
 * super admin enters the structure from a real code sample (Mahsulot toifalari → Tahrirlash).
 */
class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::groups() as $i => $group) {
            $existing = ProductGroup::withTrashed()->where('slug', $group['slug'])->first();

            if (!$existing) {
                ProductGroup::create($group + ['sort_order' => $i + 1]);
                continue;
            }

            $missing = collect($group)->only(['name_uz', 'name_ru', 'name_zh', 'structure', 'sample_code'])
                ->filter(fn ($value, $key) => blank($existing->{$key}) && filled($value));
            if ($missing->isNotEmpty()) {
                $existing->update($missing->all());
            }
        }
    }

    public static function groups(): array
    {
        $gtin = ['ai' => '01', 'label' => 'GTIN', 'length' => 14, 'variable' => false];
        $longTail = [
            ['ai' => '91', 'label' => 'Verification key', 'length' => 4, 'variable' => true],
            ['ai' => '92', 'label' => 'Verification code', 'length' => 44, 'variable' => true],
        ];

        return [
            [
                // ПКМ № 148 (02.04.2022), CRPT Turon help center; confirmed by client files
                'slug' => 'household_appliances',
                'name_uz' => 'Maishiy texnika', 'name_ru' => 'Бытовая техника', 'name_zh' => '家用电器',
                'code_format' => 'standard_long', 'is_active' => true,
                'structure' => [$gtin, ['ai' => '21', 'label' => 'Serial', 'length' => 20, 'variable' => true], ...$longTail],
                'sample_code' => '010432345678817621AF9-&eDmMbl?6CkOyAEB91UZF092TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=',
            ],
            [
                // consumer pack; confirmed by the client file codes_success.csv (2520 codes)
                'slug' => 'tobacco',
                'name_uz' => 'Tamaki mahsulotlari', 'name_ru' => 'Табачная продукция', 'name_zh' => '烟草制品',
                'code_format' => 'tobacco_short', 'is_active' => true,
                'structure' => [$gtin, ['ai' => '21', 'label' => 'Serial', 'length' => 7, 'variable' => false], ['ai' => '', 'label' => 'Crypto tail', 'length' => 13, 'variable' => false]],
                'sample_code' => '0106291071140717217SvU.!KozR5"QSkFJ"/=',
            ],
            [
                // GS1 pharma format (serial 13 + 91/92), as in the project spec
                'slug' => 'medicines',
                'name_uz' => 'Dori vositalari', 'name_ru' => 'Лекарственные средства', 'name_zh' => '药品',
                'code_format' => 'standard_long', 'is_active' => true,
                'structure' => [$gtin, ['ai' => '21', 'label' => 'Serial', 'length' => 13, 'variable' => true], ...$longTail],
                'sample_code' => null,
            ],
            // Not confirmed yet: inactive until a real code sample gives the structure.
            ['slug' => 'alcohol', 'name_uz' => 'Alkogol mahsulotlari', 'name_ru' => 'Алкогольная продукция', 'name_zh' => '酒类产品', 'code_format' => 'custom', 'is_active' => false, 'structure' => null, 'sample_code' => null],
            ['slug' => 'beer', 'name_uz' => 'Pivo va pivo ichimliklari', 'name_ru' => 'Пиво и пивные напитки', 'name_zh' => '啤酒及啤酒饮料', 'code_format' => 'custom', 'is_active' => false, 'structure' => null, 'sample_code' => null],
            ['slug' => 'water_drinks', 'name_uz' => 'Suv va alkogolsiz ichimliklar', 'name_ru' => 'Вода и безалкогольные напитки', 'name_zh' => '饮用水及软饮料', 'code_format' => 'custom', 'is_active' => false, 'structure' => null, 'sample_code' => null],
        ];
    }
}
