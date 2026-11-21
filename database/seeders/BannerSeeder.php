<?php

namespace Database\Seeders;

use App\Domain\Content\Banner;
use Illuminate\Database\Seeder;

/**
 * Home banners: the default one (the built-in hero) plus the brand banners for the owner's equipment.
 * Safe to run again: each banner is created only if missing, edits in Admin → Bannerlar are kept.
 * Pictures: official product photos in public/images/banners (shipped with the code).
 */
class BannerSeeder extends Seeder
{
    public function run(): void
    {
        Banner::firstOrCreate(['is_default' => true], ['is_active' => true, 'sort_order' => 0, 'style' => 'light']);

        foreach ($this->brands() as $i => $banner) {
            $row = Banner::firstOrCreate(['is_default' => false, 'title_uz' => $banner['title_uz']], $banner + [
                'url' => '/#offers', 'is_sponsored' => false, 'is_active' => true, 'sort_order' => $i + 1,
            ]);
            if (!$row->image_path) {
                $row->update(['image_path' => $banner['image_path']]); // a banner seeded before the pictures existed
            }
        }
    }

    private function brands(): array
    {
        return [
            [
                'style'     => 'dark',
                'image_path'=> 'images/banners/zebra-zd421.jpg',
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
                'image_path'=> 'images/banners/godex-g500.png',
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
            [
                'style'     => 'light',
                'image_path'=> 'images/banners/zebra-ds2278.jpg',
                'title_uz'  => 'Zebra DS2278 simsiz skaneri',
                'title_ru'  => 'Беспроводной сканер Zebra DS2278',
                'title_zh'  => 'Zebra DS2278 无线扫描枪',
                'text_uz'   => "1D va 2D shtrix-kodlarni, jumladan DataMatrix'ni o'qiydigan simsiz qo'l skaneri: Bluetooth va zaryadlash bazasi bilan. Kassa, ombor va chop etilgan Asl Belgisi kodlarini tekshirish uchun.",
                'text_ru'   => 'Беспроводной ручной сканер 1D и 2D штрихкодов, включая DataMatrix: Bluetooth и подставка для зарядки. Для кассы, склада и проверки напечатанных кодов Asl Belgisi.',
                'text_zh'   => '无线手持扫描枪，可读取一维和二维条码（包括 DataMatrix），配蓝牙和充电底座。适用于收银、仓库以及检查已打印的 Asl Belgisi 标识码。',
                'button_uz' => 'Skanerni tanlash',
                'button_ru' => 'Подобрать сканер',
                'button_zh' => '选择扫描枪',
            ],
            [
                'style'     => 'dark',
                'image_path'=> 'images/banners/zebra-mc3300-gun.jpg',
                'title_uz'  => 'Zebra MC3300 — pistolet dastali TSD',
                'title_ru'  => 'Zebra MC3300 — ТСД с пистолетной рукояткой',
                'title_zh'  => 'Zebra MC3300 — 枪柄式数据终端（PDA）',
                'text_uz'   => "Android ma'lumotlar terminali (PDA) pistolet dastasi bilan: ko'p skanerlanadigan ishlar uchun. Ombor, qabul qilish, inventarizatsiya va markirovka kodlari bilan ishlash uchun.",
                'text_ru'   => 'Терминал сбора данных на Android с пистолетной рукояткой — для задач с большим объёмом сканирования: склад, приёмка, инвентаризация, работа с кодами маркировки.',
                'text_zh'   => '带枪柄的 Android 数据终端（PDA），适合大量扫描的工作：仓库、收货、盘点以及标识码作业。',
                'button_uz' => 'TSD tanlash',
                'button_ru' => 'Подобрать ТСД',
                'button_zh' => '选择 PDA',
            ],
        ];
    }
}
