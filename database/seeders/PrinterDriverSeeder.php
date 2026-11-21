<?php

namespace Database\Seeders;

use App\Domain\Content\PrinterDriver;
use Illuminate\Database\Seeder;

/**
 * Driver pages for the printers we sell and service. Links are the manufacturers' official support /
 * download pages (checked 2026-11-22). Safe to run again: existing rows (by slug) are kept.
 */
class PrinterDriverSeeder extends Seeder
{
    /** Driver → marketplace products it fits (linked only when the product exists; links added in admin stay). */
    public const PRODUCTS = [
        'zebra-zd421' => ['zebra-zd421t'],
        'zebra-zt411' => ['zebra-zt411'],
        'godex-g500'  => ['godex-g500u'],
        'godex-g530'  => ['godex-g530u'],
    ];

    public function run(): void
    {
        $zebra = 'https://www.zebra.com/us/en/support-downloads/printers/';
        $rows = [
            ['Zebra', 'ZD421', 'desktop', 'ZPL', ['windows', 'macos', 'linux'], $zebra . 'desktop/zd421.html',
                "4 dyuymli stol usti printer (termo va termotransfer), 203/300 dpi.", '4-дюймовый настольный принтер (термо и термотрансфер), 203/300 dpi.', '4英寸桌面打印机（热敏和热转印），203/300 dpi。'],
            ['Zebra', 'ZD220 / ZD230', 'desktop', 'ZPL', ['windows', 'macos', 'linux'], $zebra . 'desktop/zd220.html',
                "Arzon 4 dyuymli stol usti printerlar, 203 dpi: kundalik etiketkalar uchun.", 'Доступные 4-дюймовые настольные принтеры, 203 dpi, для ежедневной печати этикеток.', '经济型4英寸桌面打印机，203 dpi，适合日常标签打印。'],
            ['Zebra', 'ZD411', 'desktop', 'ZPL', ['windows', 'macos', 'linux'], $zebra . 'desktop/zd411.html',
                "2 dyuymli ixcham stol usti printer, 203/300 dpi: kichik etiketkalar va DataMatrix uchun.", 'Компактный 2-дюймовый настольный принтер, 203/300 dpi, для маленьких этикеток и DataMatrix.', '紧凑型2英寸桌面打印机，203/300 dpi，适合小标签和 DataMatrix。'],
            ['Zebra', 'GK420t', 'desktop', 'ZPL / EPL', ['windows', 'macos', 'linux'], $zebra . 'desktop/gk420t.html',
                "Mashhur 4 dyuymli termotransfer printer, 203 dpi.", 'Популярный 4-дюймовый термотрансферный принтер, 203 dpi.', '常见的4英寸热转印打印机，203 dpi。'],
            ['Zebra', 'ZT230', 'industrial', 'ZPL', ['windows', 'macos', 'linux'], $zebra . 'industrial/zt230.html',
                "Sanoat printeri: katta hajm, metall korpus, 203/300 dpi.", 'Промышленный принтер: большие объёмы, металлический корпус, 203/300 dpi.', '工业打印机：大批量、金属机身，203/300 dpi。'],
            ['Zebra', 'ZT411', 'industrial', 'ZPL', ['windows', 'macos', 'linux'], $zebra . 'industrial/zt411.html',
                "Sanoat printeri sensorli ekran bilan, 203/300/600 dpi.", 'Промышленный принтер с сенсорным экраном, 203/300/600 dpi.', '带触摸屏的工业打印机，203/300/600 dpi。'],
            ['Godex', 'G500', 'desktop', 'EZPL', ['windows', 'macos', 'linux'], 'https://www.godexintl.com/downloads',
                "4 dyuymli stol usti printer (termo va termotransfer), 203 dpi.", '4-дюймовый настольный принтер (термо и термотрансфер), 203 dpi.', '4英寸桌面打印机（热敏和热转印），203 dpi。'],
            ['Godex', 'G530', 'desktop', 'EZPL', ['windows', 'macos', 'linux'], 'https://www.godexintl.com/downloads',
                "G500 ning 300 dpi versiyasi: mayda matn va kichik DataMatrix uchun.", 'Версия G500 с 300 dpi — для мелкого текста и маленьких DataMatrix.', 'G500 的 300 dpi 版本，适合小字和小尺寸 DataMatrix。'],
            ['Xprinter', 'XP-365B', 'desktop', 'TSPL', ['windows', 'macos', 'linux'], 'https://www.xprintertech.com/download',
                "Termo etiketka printeri 20–80 mm, 203 dpi: savdo va omborlar uchun.", 'Термопринтер этикеток 20–80 мм, 203 dpi, для торговли и складов.', '20–80 毫米热敏标签打印机，203 dpi，适合零售和仓库。'],
            ['Xprinter', 'XP-420B', 'desktop', 'TSPL', ['windows', 'macos', 'linux'], 'https://www.xprintertech.com/download',
                "4 dyuymli termo etiketka printeri, 203 dpi: yetkazib berish etiketkalari uchun.", '4-дюймовый термопринтер этикеток, 203 dpi, для транспортных этикеток.', '4英寸热敏标签打印机，203 dpi，适合物流标签。'],
        ];

        foreach ($rows as $i => [$brand, $model, $kind, $lang, $os, $url, $uz, $ru, $zh]) {
            $slug = \Illuminate\Support\Str::slug($brand . ' ' . str_replace('/', ' ', $model));
            PrinterDriver::firstOrCreate(['slug' => $slug], [
                'brand' => $brand, 'model' => $model, 'kind' => $kind, 'language' => $lang, 'os' => $os, 'driver_url' => $url,
                'summary_uz' => $uz, 'summary_ru' => $ru, 'summary_zh' => $zh, 'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }

        foreach (self::PRODUCTS as $slug => $offers) {
            $ids = \App\Domain\Offers\Models\Offer::whereIn('slug', $offers)->pluck('id');
            PrinterDriver::where('slug', $slug)->first()?->offers()->syncWithoutDetaching($ids);
        }
    }
}
