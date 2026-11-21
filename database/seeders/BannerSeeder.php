<?php

namespace Database\Seeders;

use App\Domain\Content\Banner;
use Illuminate\Database\Seeder;

/**
 * Home banners: the default one (the built-in hero) plus the brand banners for the owner's equipment.
 * Safe to run again: each banner is created only if missing, edits in Admin → Bannerlar are kept.
 * Pictures are uploaded by the admin (official brand images), so none are seeded.
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::firstOrCreate(['is_default' => true], ['is_active' => true, 'sort_order' => 0, 'style' => 'light']);

        foreach ($this->brands() as $i => $banner) {
            Banner::firstOrCreate(['is_default' => false, 'title_uz' => $banner['title_uz']], $banner + [
                'url' => '/#offers', 'is_sponsored' => false, 'is_active' => true, 'sort_order' => $i + 1,
            ]);
        }
    }

    private function brands(): array
    {
        return [
            [
                'style'     => 'dark',
                'title_uz'  => 'Zebra etiketka printerlari',
                'title_ru'  => 'Принтеры этикеток Zebra',
                'title_zh'  => 'Zebra 标签打印机',
                'text_uz'   => "Ofis, ombor va ishlab chiqarish uchun Zebra termo printerlari. Asl Belgisi DataMatrix etiketkalarini chop etishga mos. Tanlash, sozlash va texnik servis — O'zbekistonda.",
                'text_ru'   => 'Термопринтеры Zebra для офиса, склада и производства. Подходят для печати этикеток с DataMatrix Asl Belgisi. Подбор, настройка и сервис — в Узбекистане.',
                'text_zh'   => '适用于办公室、仓库和生产线的 Zebra 热敏打印机，可打印 Asl Belgisi DataMatrix 标签。在乌兹别克斯坦提供选型、调试和技术服务。',
                'button_uz' => 'Printerni tanlash',
                'button_ru' => 'Подобрать принтер',
                'button_zh' => '选择打印机',
            ],
            [
                'style'     => 'amber',
                'title_uz'  => 'Godex etiketka printerlari',
                'title_ru'  => 'Принтеры этикеток Godex',
                'title_zh'  => 'Godex 标签打印机',
                'text_uz'   => "Har kungi etiketka chop etish uchun Godex printerlari: ribbon va etiketkalar bilan birga. Asl Belgisi kodlari uchun ham mos. Tanlash, sozlash va texnik servis — O'zbekistonda.",
                'text_ru'   => 'Принтеры Godex для ежедневной печати этикеток — вместе с риббонами и этикетками. Подходят и для кодов Asl Belgisi. Подбор, настройка и сервис — в Узбекистане.',
                'text_zh'   => 'Godex 打印机适合日常标签打印，可同时提供碳带和标签，也适用于 Asl Belgisi 标识码。在乌兹别克斯坦提供选型、调试和技术服务。',
                'button_uz' => 'Printerni tanlash',
                'button_ru' => 'Подобрать принтер',
                'button_zh' => '选择打印机',
            ],
        ];
    }
}
