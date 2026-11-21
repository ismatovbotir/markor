<?php

namespace Database\Seeders;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Support\PageCache;
use Illuminate\Database\Seeder;

/**
 * Real marketplace products: printers (Godex, Zebra, Argox, Bixolon, Honeywell, TSC, Xprinter), scanners,
 * PDA terminals and ribbons — names, descriptions and FAQ in uz/ru/zh. No prices: prices are given on request.
 *
 *   php artisan db:seed --class=MarketplaceProductsSeeder --force
 *
 * Safe to run again on production: missing products are added; existing ones only get their EMPTY fields
 * filled, so names, texts, FAQ, photos, order and visibility edited in admin stay as they are.
 * The generic placeholder items (from OfferSeeder) are hidden once, on the run that adds the real products.
 */
class MarketplaceProductsSeeder extends Seeder
{
    /** Generic items replaced by the real models below. */
    public const PLACEHOLDERS = [
        'ribbon-wax-110', 'ribbon-wax-resin-110', 'ribbon-resin-110', 'ribbon-wax-60',
        'printer-desktop-dt', 'printer-desktop-tt', 'printer-industrial', 'printer-mobile', 'printer-example-x1',
        'scanner-wired-2d', 'scanner-wireless-2d', 'scanner-presentation', 'scanner-industrial',
        'pda-android', 'pda-rugged', 'pda-pistol',
    ];

    public const PRODUCTS = [
        // printers
        [
            'category' => 'printers', 'slug' => 'godex-g500u', 'action' => 'call',
            'title' => ['Godex G500U termotransfer printer', 'Термотрансферный принтер Godex G500U', 'Godex G500U 热转印打印机'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 203 dpi, bosma kengligi 108 mm gacha, USB orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 203 dpi, ширина печати до 108 мм, подключение по USB.', '可使用碳带打印，也可在热敏标签上不用碳带打印；203 dpi，打印宽度最大 108 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'Ribbon kerakmi?', 'a_uz' => 'Termotransfer usulida kerak; termo etiketkalarga ribbonsiz chop etadi.', 'q_ru' => 'Нужен риббон?', 'a_ru' => 'Для термотрансферной печати нужен; на термоэтикетках печатает без риббона.', 'q_zh' => '需要碳带吗？', 'a_zh' => '热转印打印需要；热敏标签可不用碳带打印。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
                ['q_uz' => 'Narxi va kafolati qanday?', 'a_uz' => 'Narx, kafolat va xizmat bo\'yicha bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Какая цена и гарантия?', 'a_ru' => 'По цене, гарантии и сервису позвоните нам.', 'q_zh' => '价格和保修如何？', 'a_zh' => '价格、保修和服务请致电我们。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'godex-g530u', 'action' => 'call',
            'title' => ['Godex G530U termotransfer printer, 300 dpi', 'Термотрансферный принтер Godex G530U, 300 dpi', 'Godex G530U 热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 105,7 mm gacha, USB orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 105,7 мм, подключение по USB.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 105.7 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'G500U dan farqi nimada?', 'a_uz' => 'Aniqlik 203 dpi emas, 300 dpi: kichik DataMatrix kodlari va mayda matn aniqroq chiqadi.', 'q_ru' => 'Чем отличается от G500U?', 'a_ru' => 'Разрешение 300 dpi вместо 203 dpi: мелкие коды DataMatrix и мелкий текст печатаются чётче.', 'q_zh' => '与 G500U 有什么区别？', 'a_zh' => '分辨率为 300 dpi 而不是 203 dpi：小尺寸 DataMatrix 码和小字打印更清晰。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 30–110 mm, uzunligi 300 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 30–110 мм и длиной до 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 30–110 毫米、长度最大 300 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'godex-zx430i-plus', 'action' => 'call',
            'title' => ['Godex ZX430i+ sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Godex ZX430i+, 300 dpi', 'Godex ZX430i+ 工业热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 105,7 mm gacha; USB, RS-232 va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 105,7 мм; подключение по USB, RS-232 и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 105.7 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'G530U dan farqi nimada?', 'a_uz' => 'Sanoat sinfidagi printer: 450 m gacha ribbon sig\'adi, Ethernet va rangli ekrani bor.', 'q_ru' => 'Чем отличается от G530U?', 'a_ru' => 'Принтер промышленного класса: вмещает риббон до 450 м, есть Ethernet и цветной экран.', 'q_zh' => '与 G530U 有什么区别？', 'a_zh' => '属于工业级打印机：可装最长 450 米的碳带，带以太网和彩色屏幕。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 30–110 mm, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 30–110 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 30–110 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'godex-gx4300i', 'action' => 'call',
            'title' => ['Godex GX4300i sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Godex GX4300i, 300 dpi', 'Godex GX4300i 工业热转印打印机，300 dpi'],
            'description' => ['Katta hajmda chop etish uchun: ribbon bilan yoki termo etiketkaga ribbonsiz, 300 dpi, tezligi 305 mm/s gacha, etiketka kengligi 118 mm gacha; USB, RS-232 va Ethernet orqali ulanadi.', 'Для больших объёмов печати: через риббон или без него на термоэтикетках, 300 dpi, скорость до 305 мм/с, ширина этикетки до 118 мм; подключение по USB, RS-232 и Ethernet.', '适合大批量打印：可使用碳带，也可在热敏标签上不用碳带打印，300 dpi，速度最高 305 毫米/秒，标签宽度最大 118 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'ZX430i+ dan farqi nimada?', 'a_uz' => 'Tezroq chop etadi: 102 mm/s o\'rniga 305 mm/s gacha; 5 dyuymli sensorli ekrani bor.', 'q_ru' => 'Чем отличается от ZX430i+?', 'a_ru' => 'Печатает быстрее: до 305 мм/с вместо 102 мм/с; есть 5-дюймовый сенсорный экран.', 'q_zh' => '与 ZX430i+ 有什么区别？', 'a_zh' => '打印更快：最高 305 毫米/秒，而不是 102 毫米/秒；带 5 英寸触摸屏。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 30–110 mm, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 30–110 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 30–110 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'argox-ix4-350-pro', 'action' => 'call',
            'title' => ['Argox iX4-350 Pro sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Argox iX4-350 Pro, 300 dpi', 'Argox iX4-350 Pro 工业热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 103,7 mm gacha, etiketka kengligi 25,4–114 mm; USB, RS-232 va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 103,7 мм, ширина этикетки 25,4–114 мм; подключение по USB, RS-232 и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 103.7 毫米，标签宽度 25.4–114 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 25,4–112 mm, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 25,4–112 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 25.4–112 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Wi-Fi orqali ulanadimi?', 'a_uz' => 'Wi-Fi qo\'shimcha modul sifatida o\'rnatiladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подключается по Wi-Fi?', 'a_ru' => 'Wi-Fi ставится как дополнительный модуль; по этому вопросу позвоните нам.', 'q_zh' => '可以用 Wi-Fi 连接吗？', 'a_zh' => 'Wi-Fi 为选配模块；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'zebra-zt411', 'action' => 'call',
            'title' => ['Zebra ZT411 sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Zebra ZT411, 300 dpi', 'Zebra ZT411 工业热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 104 mm gacha, etiketka kengligi 25,4–114 mm; USB, RS-232, Ethernet va Bluetooth orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 104 мм, ширина этикетки 25,4–114 мм; подключение по USB, RS-232, Ethernet и Bluetooth.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 104 毫米，标签宽度 25.4–114 毫米；USB、RS-232、以太网和蓝牙连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 51–110 mm, uzunligi 450 m bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 51–110 мм и длиной 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 51–110 毫米、长 450 米的碳带。'],
                ['q_uz' => 'Boshqa aniqlikda ham bormi?', 'a_uz' => 'Ha, ZT411 203 va 600 dpi bosma kallagi bilan ham chiqariladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Есть ли другое разрешение?', 'a_ru' => 'Да, ZT411 выпускается также с печатающей головкой 203 и 600 dpi; по этому вопросу позвоните нам.', 'q_zh' => '有其他分辨率吗？', 'a_zh' => '有，ZT411 也提供 203 和 600 dpi 打印头；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'zebra-zd421t', 'action' => 'call',
            'title' => ['Zebra ZD421t stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Zebra ZD421t, 300 dpi', 'Zebra ZD421t 桌面热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan chop etadi; 300 dpi, bosma kengligi 106 mm gacha, etiketka kengligi 15–112 mm, USB orqali ulanadi.', 'Печатает через риббон; 300 dpi, ширина печати до 106 мм, ширина этикетки 15–112 мм, подключение по USB.', '使用碳带打印；300 dpi，打印宽度最大 106 毫米，标签宽度 15–112 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 33,8–109,2 mm, uzunligi 74 m yoki 300 m bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 33,8–109,2 мм и длиной 74 м или 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 33.8–109.2 毫米、长 74 米或 300 米的碳带。'],
                ['q_uz' => 'Tarmoq yoki Wi-Fi orqali ulanadimi?', 'a_uz' => 'Ethernet va Wi-Fi qo\'shimcha modul sifatida o\'rnatiladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подключается по сети или Wi-Fi?', 'a_ru' => 'Ethernet и Wi-Fi ставятся как дополнительные модули; по этому вопросу позвоните нам.', 'q_zh' => '可以用网络或 Wi-Fi 连接吗？', 'a_zh' => '以太网和 Wi-Fi 为选配模块；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'bixolon-xd5-43t', 'action' => 'call',
            'title' => ['Bixolon XD5-43t stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Bixolon XD5-43t, 300 dpi', 'Bixolon XD5-43t 桌面热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 105,7 mm gacha, etiketka kengligi 15–118 mm; USB, RS-232 va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 105,7 мм, ширина этикетки 15–118 мм; подключение по USB, RS-232 и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 105.7 毫米，标签宽度 15–118 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 33–110 mm, uzunligi 300 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 33–110 мм и длиной до 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 33–110 毫米、长度最大 300 米的碳带。'],
                ['q_uz' => 'Chop etish tezligi qanday?', 'a_uz' => '102 mm/s gacha.', 'q_ru' => 'Какая скорость печати?', 'a_ru' => 'До 102 мм/с.', 'q_zh' => '打印速度是多少？', 'a_zh' => '最高 102 毫米/秒。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'bixolon-xt5-43t', 'action' => 'call',
            'title' => ['Bixolon XT5-43t sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Bixolon XT5-43t, 300 dpi', 'Bixolon XT5-43t 工业热转印打印机，300 dpi'],
            'description' => ['Katta hajmda chop etish uchun: ribbon bilan yoki termo etiketkaga ribbonsiz, 300 dpi, tezligi 254 mm/s gacha, bosma kengligi 105,7 mm gacha, etiketka kengligi 25–114 mm; USB, Ethernet va RS-232 orqali ulanadi.', 'Для больших объёмов печати: через риббон или без него на термоэтикетках, 300 dpi, скорость до 254 мм/с, ширина печати до 105,7 мм, ширина этикетки 25–114 мм; подключение по USB, Ethernet и RS-232.', '适合大批量打印：可使用碳带，也可在热敏标签上不用碳带打印，300 dpi，速度最高 254 毫米/秒，打印宽度最大 105.7 毫米，标签宽度 25–114 毫米；USB、以太网和 RS-232 连接。'],
            'faq' => [
                ['q_uz' => 'XD5-43t dan farqi nimada?', 'a_uz' => 'Sanoat sinfidagi printer: tezligi 102 mm/s o\'rniga 254 mm/s gacha, ribbon 300 m o\'rniga 450 m gacha sig\'adi.', 'q_ru' => 'Чем отличается от XD5-43t?', 'a_ru' => 'Принтер промышленного класса: скорость до 254 мм/с вместо 102 мм/с, вмещает риббон до 450 м вместо 300 м.', 'q_zh' => '与 XD5-43t 有什么区别？', 'a_zh' => '属于工业级打印机：速度最高 254 毫米/秒，而不是 102 毫米/秒；可装最长 450 米的碳带，而不是 300 米。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 110 mm gacha, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной до 110 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽度最大 110 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'bixolon-xt3-43', 'action' => 'call',
            'title' => ['Bixolon XT3-43 sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер Bixolon XT3-43, 300 dpi', 'Bixolon XT3-43 工业热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, tezligi 152 mm/s gacha, bosma kengligi 105,7 mm gacha, etiketka kengligi 20–114 mm; USB, RS-232 va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, скорость до 152 мм/с, ширина печати до 105,7 мм, ширина этикетки 20–114 мм; подключение по USB, RS-232 и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，速度最高 152 毫米/秒，打印宽度最大 105.7 毫米，标签宽度 20–114 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'XT5-43t dan farqi nimada?', 'a_uz' => 'Ixchamroq sanoat printeri: tezligi 254 mm/s o\'rniga 152 mm/s gacha.', 'q_ru' => 'Чем отличается от XT5-43t?', 'a_ru' => 'Более компактный промышленный принтер: скорость до 152 мм/с вместо 254 мм/с.', 'q_zh' => '与 XT5-43t 有什么区别？', 'a_zh' => '更紧凑的工业打印机：速度最高 152 毫米/秒，而不是 254 毫米/秒。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 40–110 mm, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 40–110 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 40–110 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'argox-cp-3140ex-pro', 'action' => 'call',
            'title' => ['Argox CP-3140EX Pro stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Argox CP-3140EX Pro, 300 dpi', 'Argox CP-3140EX Pro 桌面热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, bosma kengligi 105,7 mm gacha, etiketka kengligi 20–118 mm; USB, RS-232 va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, ширина печати до 105,7 мм, ширина этикетки 20–118 мм; подключение по USB, RS-232 и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，打印宽度最大 105.7 毫米，标签宽度 20–118 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 25,4–110 mm, uzunligi 300 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 25,4–110 мм и длиной до 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 25.4–110 毫米、长度最大 300 米的碳带。'],
                ['q_uz' => 'iX4-350 Pro dan farqi nimada?', 'a_uz' => 'Bu stol usti printeri: ribbon 450 m o\'rniga 300 m gacha sig\'adi.', 'q_ru' => 'Чем отличается от iX4-350 Pro?', 'a_ru' => 'Это настольный принтер: вмещает риббон до 300 м вместо 450 м.', 'q_zh' => '与 iX4-350 Pro 有什么区别？', 'a_zh' => '这是桌面打印机：可装最长 300 米的碳带，而不是 450 米。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'honeywell-pc42e-t-300dpi', 'action' => 'call',
            'title' => ['Honeywell PC42E-T stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Honeywell PC42E-T, 300 dpi', 'Honeywell PC42E-T 桌面热转印打印机，300 dpi'],
            'description' => ['4 dyuymli printer: ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, tezligi 127 mm/s gacha; USB va Ethernet orqali ulanadi.', '4-дюймовый принтер: печатает через риббон или без него на термоэтикетках; 300 dpi, скорость до 127 мм/с; подключение по USB и Ethernet.', '4 英寸打印机：可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，速度最高 127 毫米/秒；USB 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Uzunligi 300 m gacha bo\'lgan ribbon; o\'zak diametri 0,5 yoki 1 dyuym.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон длиной до 300 м; диаметр втулки 0,5 или 1 дюйм.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '长度最大 300 米的碳带；轴芯直径 0.5 或 1 英寸。'],
                ['q_uz' => 'Wi-Fi yoki Bluetooth bormi?', 'a_uz' => 'Qo\'shimcha modul sifatida o\'rnatiladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Есть Wi-Fi или Bluetooth?', 'a_ru' => 'Ставятся как дополнительный модуль; по этому вопросу позвоните нам.', 'q_zh' => '有 Wi-Fi 或蓝牙吗？', 'a_zh' => '为选配模块；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'honeywell-pc45t-300dpi', 'action' => 'call',
            'title' => ['Honeywell PC45t stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Honeywell PC45t, 300 dpi', 'Honeywell PC45t 桌面热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, tezligi 152 mm/s gacha, bosma kengligi 106 mm gacha, etiketka kengligi 118 mm gacha; USB va Ethernet orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, скорость до 152 мм/с, ширина печати до 106 мм, ширина этикетки до 118 мм; подключение по USB и Ethernet.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，速度最高 152 毫米/秒，打印宽度最大 106 毫米，标签宽度最大 118 毫米；USB 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'PC42E-T dan farqi nimada?', 'a_uz' => 'Tezroq chop etadi: 127 mm/s o\'rniga 152 mm/s gacha; 3,5 dyuymli rangli ekrani bor.', 'q_ru' => 'Чем отличается от PC42E-T?', 'a_ru' => 'Печатает быстрее: до 152 мм/с вместо 127 мм/с; есть цветной экран 3,5 дюйма.', 'q_zh' => '与 PC42E-T 有什么区别？', 'a_zh' => '打印更快：最高 152 毫米/秒，而不是 127 毫米/秒；带 3.5 英寸彩色屏幕。'],
                ['q_uz' => 'RS-232 orqali ulanadimi?', 'a_uz' => 'RS-232 qo\'shimcha interfeys sifatida o\'rnatiladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подключается по RS-232?', 'a_ru' => 'RS-232 ставится как дополнительный интерфейс; по этому вопросу позвоните нам.', 'q_zh' => '可以用 RS-232 连接吗？', 'a_zh' => 'RS-232 为选配接口；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'honeywell-px940v-600dpi', 'action' => 'call',
            'title' => ['Honeywell PX940V sanoat printeri, 600 dpi, o\'rnatilgan verifikator bilan', 'Промышленный принтер Honeywell PX940V, 600 dpi, со встроенным верификатором', 'Honeywell PX940V 工业打印机，600 dpi，内置条码检测器'],
            'description' => ['Chop etilgan har bir kodni o\'rnatilgan verifikator tekshiradi; ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi, 600 dpi, bosma kengligi 105,7 mm gacha; USB, RS-232 va Ethernet orqali ulanadi.', 'Встроенный верификатор проверяет каждый напечатанный код; печатает через риббон или без него на термоэтикетках, 600 dpi, ширина печати до 105,7 мм; подключение по USB, RS-232 и Ethernet.', '内置检测器检查每一个打印出的条码；可使用碳带打印，也可在热敏标签上不用碳带打印，600 dpi，打印宽度最大 105.7 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'Verifikator DataMatrix kodlarini tekshiradimi?', 'a_uz' => 'Ha, Data Matrix va QR kodlarni tekshiradi; 2D kodda modul o\'lchami kamida 15 mil (taxminan 0,38 mm) bo\'lishi kerak.', 'q_ru' => 'Верификатор проверяет коды DataMatrix?', 'a_ru' => 'Да, проверяет Data Matrix и QR-коды; размер модуля 2D-кода должен быть не меньше 15 mil (около 0,38 мм).', 'q_zh' => '检测器能检查 DataMatrix 码吗？', 'a_zh' => '可以，检查 Data Matrix 和 QR 码；二维码的模块尺寸不得小于 15 mil（约 0.38 毫米）。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 51–110 mm, uzunligi 450 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 51–110 мм и длиной до 450 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 51–110 毫米、长度最大 450 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'tsc-te200', 'action' => 'call',
            'title' => ['TSC TE200 stol usti termotransfer printeri, 203 dpi', 'Настольный термотрансферный принтер TSC TE200, 203 dpi', 'TSC TE200 桌面热转印打印机，203 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 203 dpi, tezligi 152 mm/s gacha, bosma kengligi 108 mm gacha, etiketka kengligi 20–112 mm, USB orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 203 dpi, скорость до 152 мм/с, ширина печати до 108 мм, ширина этикетки 20–112 мм, подключение по USB.', '可使用碳带打印，也可在热敏标签上不用碳带打印；203 dpi，速度最高 152 毫米/秒，打印宽度最大 108 毫米，标签宽度 20–112 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 40–110 mm, uzunligi 300 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной 40–110 мм и длиной до 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽 40–110 毫米、长度最大 300 米的碳带。'],
                ['q_uz' => '300 dpi versiyasi bormi?', 'a_uz' => 'Shu seriyadagi 300 dpi model TE300 deb ataladi; bu haqda bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Есть версия 300 dpi?', 'a_ru' => 'Модель на 300 dpi в этой серии называется TE300; по этому вопросу позвоните нам.', 'q_zh' => '有 300 dpi 版本吗？', 'a_zh' => '该系列的 300 dpi 型号为 TE300；详情请致电我们。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'tsc-mx341p', 'action' => 'call',
            'title' => ['TSC MX341P sanoat termotransfer printeri, 300 dpi', 'Промышленный термотрансферный принтер TSC MX341P, 300 dpi', 'TSC MX341P 工业热转印打印机，300 dpi'],
            'description' => ['Katta hajmda chop etish uchun: ribbon bilan yoki termo etiketkaga ribbonsiz, 300 dpi, tezligi 356 mm/s gacha, bosma kengligi 104 mm gacha, etiketka kengligi 20–114 mm; USB, RS-232 va Ethernet orqali ulanadi.', 'Для больших объёмов печати: через риббон или без него на термоэтикетках, 300 dpi, скорость до 356 мм/с, ширина печати до 104 мм, ширина этикетки 20–114 мм; подключение по USB, RS-232 и Ethernet.', '适合大批量打印：可使用碳带，也可在热敏标签上不用碳带打印，300 dpi，速度最高 356 毫米/秒，打印宽度最大 104 毫米，标签宽度 20–114 毫米；USB、RS-232 和以太网连接。'],
            'faq' => [
                ['q_uz' => 'TE200 dan farqi nimada?', 'a_uz' => 'Sanoat sinfidagi printer: 300 dpi, tezligi 356 mm/s gacha, ribbon 300 m o\'rniga 600 m gacha sig\'adi.', 'q_ru' => 'Чем отличается от TE200?', 'a_ru' => 'Принтер промышленного класса: 300 dpi, скорость до 356 мм/с, вмещает риббон до 600 м вместо 300 м.', 'q_zh' => '与 TE200 有什么区别？', 'a_zh' => '属于工业级打印机：300 dpi，速度最高 356 毫米/秒，可装最长 600 米的碳带，而不是 300 米。'],
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Uzunligi 600 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон длиной до 600 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '长度最大 600 米的碳带。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'xprinter-xp-470b', 'action' => 'call',
            'title' => ['Xprinter XP-470B termo printer, 203 dpi', 'Термопринтер Xprinter XP-470B, 203 dpi', 'Xprinter XP-470B 热敏打印机，203 dpi'],
            'description' => ['Termo etiketkalarga chop etadi, ribbon kerak emas; 203 dpi, bosma kengligi 108 mm gacha, etiketka kengligi 118 mm gacha, USB orqali ulanadi.', 'Печатает на термоэтикетках, риббон не нужен; 203 dpi, ширина печати до 108 мм, ширина этикетки до 118 мм, подключение по USB.', '在热敏标签上打印，无需碳带；203 dpi，打印宽度最大 108 毫米，标签宽度最大 118 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'Ribbon kerakmi?', 'a_uz' => 'Yo\'q, faqat termo etiketkalarga chop etadi.', 'q_ru' => 'Нужен риббон?', 'a_ru' => 'Нет, печатает только на термоэтикетках.', 'q_zh' => '需要碳带吗？', 'a_zh' => '不需要，只在热敏标签上打印。'],
                ['q_uz' => 'Bosma qancha vaqt saqlanadi?', 'a_uz' => 'Termo etiketkadagi bosma issiqlik va quyosh nuridan vaqt o\'tishi bilan xiralashadi; uzoq saqlanadigan etiketkalar uchun termotransfer printer oling.', 'q_ru' => 'Как долго держится печать?', 'a_ru' => 'Печать на термоэтикетке со временем выцветает от тепла и солнечного света; для этикеток длительного хранения берите термотрансферный принтер.', 'q_zh' => '打印内容能保存多久？', 'a_zh' => '热敏标签上的打印内容受热和日晒后会逐渐褪色；需要长期保存的标签请选热转印打印机。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        [
            'category' => 'printers', 'slug' => 'xprinter-xp-t451e', 'action' => 'call',
            'title' => ['Xprinter XP-T451E stol usti termotransfer printeri, 300 dpi', 'Настольный термотрансферный принтер Xprinter XP-T451E, 300 dpi', 'Xprinter XP-T451E 桌面热转印打印机，300 dpi'],
            'description' => ['Ribbon bilan yoki termo etiketkaga ribbonsiz chop etadi; 300 dpi, tezligi 101,6 mm/s gacha, bosma kengligi 104 mm gacha, USB orqali ulanadi.', 'Печатает через риббон или без него на термоэтикетках; 300 dpi, скорость до 101,6 мм/с, ширина печати до 104 мм, подключение по USB.', '可使用碳带打印，也可在热敏标签上不用碳带打印；300 dpi，速度最高 101.6 毫米/秒，打印宽度最大 104 毫米，USB 连接。'],
            'faq' => [
                ['q_uz' => 'Qaysi ribbon mos keladi?', 'a_uz' => 'Kengligi 110 mm gacha, uzunligi 300 m gacha bo\'lgan ribbon.', 'q_ru' => 'Какой риббон подходит?', 'a_ru' => 'Риббон шириной до 110 мм и длиной до 300 м.', 'q_zh' => '适用哪种碳带？', 'a_zh' => '宽度最大 110 毫米、长度最大 300 米的碳带。'],
                ['q_uz' => 'XP-470B dan farqi nimada?', 'a_uz' => 'Ribbon bilan ham chop etadi va aniqligi 203 dpi emas, 300 dpi.', 'q_ru' => 'Чем отличается от XP-470B?', 'a_ru' => 'Печатает и через риббон, разрешение 300 dpi вместо 203 dpi.', 'q_zh' => '与 XP-470B 有什么区别？', 'a_zh' => '也可使用碳带打印，分辨率为 300 dpi 而不是 203 dpi。'],
                ['q_uz' => 'Markor bilan ishlaydimi?', 'a_uz' => 'Ha: Windows\'dagi printer sifatida Markor\'dan to\'g\'ridan-to\'g\'ri yoki PDF orqali chop etadi.', 'q_ru' => 'Работает с Markor?', 'a_ru' => 'Да: печатает из Markor напрямую как принтер Windows или через PDF.', 'q_zh' => '能配合 Markor 使用吗？', 'a_zh' => '可以：作为 Windows 打印机直接从 Markor 打印，或通过 PDF 打印。'],
            ],
        ],
        // scanners
        [
            'category' => 'scanners', 'slug' => 'newland-hr33-bluetooth', 'action' => 'call',
            'title' => ['Newland HR33 Marlin simsiz 2D skaner, Bluetooth', 'Беспроводной 2D-сканер Newland HR33 Marlin, Bluetooth', 'Newland HR33 Marlin 无线 2D 扫描器，蓝牙'],
            'description' => ['1D va 2D kodlarni, jumladan GS1 DataMatrix\'ni o\'qiydi; megapikselli sensor va lazer nishon, Bluetooth 5.0 orqali ochiq joyda 100 m gacha ishlaydi, zaryadlash tagligi USB orqali ulanadi.', 'Читает 1D- и 2D-коды, включая GS1 DataMatrix; мегапиксельный сенсор и лазерный прицел, работает по Bluetooth 5.0 на расстоянии до 100 м на открытом пространстве, зарядная подставка подключается по USB.', '可读取一维码和二维码，包括 GS1 DataMatrix；百万像素传感器和激光瞄准，通过蓝牙 5.0 在空旷处可达 100 米，充电底座通过 USB 连接。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, GS1 DataMatrix\'ni o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает GS1 DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 GS1 DataMatrix。'],
                ['q_uz' => 'Kompyuterga qanday ulanadi?', 'a_uz' => 'Zaryadlash tagligi USB orqali ulanadi, skaner klaviatura kabi ishlaydi.', 'q_ru' => 'Как подключается к компьютеру?', 'a_ru' => 'Зарядная подставка подключается по USB, сканер работает как клавиатура.', 'q_zh' => '如何连接电脑？', 'a_zh' => '充电底座通过 USB 连接，扫描器像键盘一样工作。'],
                ['q_uz' => 'Chang va namlikdan himoyasi qanday?', 'a_uz' => 'Himoya darajasi IP52.', 'q_ru' => 'Какая защита от пыли и влаги?', 'a_ru' => 'Степень защиты IP52.', 'q_zh' => '防尘防水等级如何？', 'a_zh' => '防护等级为 IP52。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'zebra-ds2278', 'action' => 'call',
            'title' => ['Zebra DS2278 simsiz 2D skaner, zaryadlash tagligi bilan', 'Беспроводной 2D-сканер Zebra DS2278 с зарядной подставкой', 'Zebra DS2278 无线 2D 扫描器，带充电底座'],
            'description' => ['1D va 2D kodlarni, jumladan DataMatrix\'ni o\'qiydi; Bluetooth orqali ishlaydi, batareyasi 14 soatga yetadi, zaryadlash tagligi kompyuterga USB orqali ulanadi.', 'Читает 1D- и 2D-коды, включая DataMatrix; работает по Bluetooth, батареи хватает на 14 часов, зарядная подставка подключается к компьютеру по USB.', '可读取一维码和二维码，包括 DataMatrix；通过蓝牙工作，电池可用 14 小时，充电底座通过 USB 连接电脑。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, DataMatrix kodlarini o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает коды DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 DataMatrix 码。'],
                ['q_uz' => 'Qancha masofadan o\'qiydi?', 'a_uz' => 'Kodga qarab 1,2 sm dan 36,8 sm gacha.', 'q_ru' => 'С какого расстояния читает?', 'a_ru' => 'От 1,2 см до 36,8 см, в зависимости от кода.', 'q_zh' => '读取距离是多少？', 'a_zh' => '1.2 厘米至 36.8 厘米，视条码而定。'],
                ['q_uz' => 'Planshet yoki telefonga ulanadimi?', 'a_uz' => 'Ha, Bluetooth\'li kompyuter, planshet yoki smartfonga bitta kodni skanerlash orqali ulanadi.', 'q_ru' => 'Подключается к планшету или телефону?', 'a_ru' => 'Да, к компьютеру, планшету или смартфону с Bluetooth подключается сканированием одного кода.', 'q_zh' => '可以连接平板或手机吗？', 'a_zh' => '可以，扫描一个条码即可与带蓝牙的电脑、平板或智能手机配对。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'zebra-ds2208', 'action' => 'call',
            'title' => ['Zebra DS2208 simli 2D skaner, USB', 'Проводной 2D-сканер Zebra DS2208, USB', 'Zebra DS2208 有线 2D 扫描器，USB'],
            'description' => ['1D va 2D kodlarni, jumladan DataMatrix\'ni o\'qiydi; kompyuterga USB kabel orqali ulanadi, kassa va ish joyi uchun.', 'Читает 1D- и 2D-коды, включая DataMatrix; подключается к компьютеру USB-кабелем, для кассы и рабочего места.', '可读取一维码和二维码，包括 DataMatrix；通过 USB 线连接电脑，适用于收银台和工位。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, DataMatrix kodlarini o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает коды DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 DataMatrix 码。'],
                ['q_uz' => 'Qancha masofadan o\'qiydi?', 'a_uz' => 'Kodga qarab 1,2 sm dan 36,8 sm gacha.', 'q_ru' => 'С какого расстояния читает?', 'a_ru' => 'От 1,2 см до 36,8 см, в зависимости от кода.', 'q_zh' => '读取距离是多少？', 'a_zh' => '1.2 厘米至 36.8 厘米，视条码而定。'],
                ['q_uz' => 'DS2278 dan farqi nimada?', 'a_uz' => 'DS2208 simli, DS2278 esa Bluetooth orqali simsiz ishlaydi.', 'q_ru' => 'Чем отличается от DS2278?', 'a_ru' => 'DS2208 проводной, а DS2278 работает без провода по Bluetooth.', 'q_zh' => '与 DS2278 有什么区别？', 'a_zh' => 'DS2208 为有线，DS2278 通过蓝牙无线工作。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'zebra-ds9308', 'action' => 'call',
            'title' => ['Zebra DS9308 statsionar 2D skaner, USB', 'Стационарный 2D-сканер Zebra DS9308, USB', 'Zebra DS9308 固定式 2D 扫描器，USB'],
            'description' => ['Stolda turadi va qo\'lda ushlamasdan o\'qiydi: 1D va 2D kodlar, jumladan DataMatrix; megapikselli sensor (1280 × 800), kompyuterga USB orqali ulanadi.', 'Стоит на столе и читает без рук: 1D- и 2D-коды, включая DataMatrix; мегапиксельный сенсор (1280 × 800), подключается к компьютеру по USB.', '放在桌面上免手持读取：一维码和二维码，包括 DataMatrix；百万像素传感器（1280 × 800），通过 USB 连接电脑。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, DataMatrix kodlarini o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает коды DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 DataMatrix 码。'],
                ['q_uz' => 'Qanchalik mayda DataMatrix\'ni o\'qiydi?', 'a_uz' => 'Modul o\'lchami 5 mil (taxminan 0,13 mm) dan boshlab.', 'q_ru' => 'Насколько мелкий DataMatrix читает?', 'a_ru' => 'С размером модуля от 5 mil (около 0,13 мм).', 'q_zh' => '能读取多小的 DataMatrix？', 'a_zh' => '模块尺寸从 5 mil（约 0.13 毫米）起。'],
                ['q_uz' => 'Kimga mos keladi?', 'a_uz' => 'Kodlarni ketma-ket skanerlaydigan ish joylariga, masalan mahsulotni qutiga joylashda.', 'q_ru' => 'Кому подходит?', 'a_ru' => 'Рабочим местам, где коды сканируют потоком, например при укладке товара в короб.', 'q_zh' => '适合谁？', 'a_zh' => '适合连续扫码的工位，例如把产品装箱时。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'honeywell-voyager-xp-1472g', 'action' => 'call',
            'title' => ['Honeywell Voyager XP 1472g simsiz 2D skaner, Bluetooth', 'Беспроводной 2D-сканер Honeywell Voyager XP 1472g, Bluetooth', 'Honeywell Voyager XP 1472g 无线 2D 扫描器，蓝牙'],
            'description' => ['1D va 2D kodlarni, jumladan DataMatrix\'ni o\'qiydi; Bluetooth 4.2 orqali ishlaydi, zaryadlash bazasi kompyuterga USB orqali ulanadi; himoya darajasi IP52.', 'Читает 1D- и 2D-коды, включая DataMatrix; работает по Bluetooth 4.2, зарядная база подключается к компьютеру по USB; степень защиты IP52.', '可读取一维码和二维码，包括 DataMatrix；通过蓝牙 4.2 工作，充电底座通过 USB 连接电脑；防护等级 IP52。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, DataMatrix kodlarini o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает коды DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 DataMatrix 码。'],
                ['q_uz' => 'DataMatrix\'ni qancha masofadan o\'qiydi?', 'a_uz' => 'Modul o\'lchami 10 mil bo\'lgan kodni 1 sm dan 16,5 sm gacha.', 'q_ru' => 'С какого расстояния читает DataMatrix?', 'a_ru' => 'Код с модулем 10 mil — от 1 см до 16,5 см.', 'q_zh' => 'DataMatrix 的读取距离是多少？', 'a_zh' => '模块尺寸为 10 mil 的码：1 厘米至 16.5 厘米。'],
                ['q_uz' => 'Kompyuterga qanday ulanadi?', 'a_uz' => 'Zaryadlash bazasi USB orqali ulanadi, skaner klaviatura kabi ishlaydi.', 'q_ru' => 'Как подключается к компьютеру?', 'a_ru' => 'Зарядная база подключается по USB, сканер работает как клавиатура.', 'q_zh' => '如何连接电脑？', 'a_zh' => '充电底座通过 USB 连接，扫描器像键盘一样工作。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'honeywell-orbit-hf680', 'action' => 'call',
            'title' => ['Honeywell Orbit HF680 statsionar 2D skaner, USB', 'Стационарный 2D-сканер Honeywell Orbit HF680, USB', 'Honeywell Orbit HF680 固定式 2D 扫描器，USB'],
            'description' => ['Stolda turadi va qo\'lda ushlamasdan o\'qiydi: 1D va 2D kodlar, jumladan DataMatrix; megapikselli sensor (1280 × 800), kompyuterga USB orqali ulanadi.', 'Стоит на столе и читает без рук: 1D- и 2D-коды, включая DataMatrix; мегапиксельный сенсор (1280 × 800), подключается к компьютеру по USB.', '放在桌面上免手持读取：一维码和二维码，包括 DataMatrix；百万像素传感器（1280 × 800），通过 USB 连接电脑。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, DataMatrix kodlarini o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает коды DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取 DataMatrix 码。'],
                ['q_uz' => 'Qanday ulanadi?', 'a_uz' => 'USB orqali; RS-232 orqali ulash ham mumkin.', 'q_ru' => 'Как подключается?', 'a_ru' => 'По USB; возможно подключение и по RS-232.', 'q_zh' => '如何连接？', 'a_zh' => '通过 USB；也可通过 RS-232 连接。'],
                ['q_uz' => 'Kimga mos keladi?', 'a_uz' => 'Kodlarni ketma-ket skanerlaydigan ish joylariga, masalan kassa yoki mahsulotni qutiga joylash.', 'q_ru' => 'Кому подходит?', 'a_ru' => 'Рабочим местам, где коды сканируют потоком, например касса или укладка товара в короб.', 'q_zh' => '适合谁？', 'a_zh' => '适合连续扫码的工位，例如收银台或产品装箱。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'mindeo-md6600-hd', 'action' => 'call',
            'title' => ['Mindeo MD6600-HD simli 2D skaner, USB', 'Проводной 2D-сканер Mindeo MD6600-HD, USB', 'Mindeo MD6600-HD 有线 2D 扫描器，USB'],
            'description' => ['1D va 2D kodlarni, jumladan telefon ekranidagi kodlarni o\'qiydi; mayda kodlar uchun HD versiya, sensor 1280 × 800, 2 m kabel bilan USB orqali ulanadi.', 'Читает 1D- и 2D-коды, в том числе с экрана телефона; версия HD для мелких кодов, сенсор 1280 × 800, подключается по USB кабелем 2 м.', '可读取一维码和二维码，包括手机屏幕上的码；HD 版本用于小尺寸条码，传感器 1280 × 800，通过 2 米线缆 USB 连接。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlarini o\'qiydimi?', 'a_uz' => 'Ha, 2D kodlarni, jumladan DataMatrix\'ni o\'qiydi.', 'q_ru' => 'Читает коды Asl Belgisi?', 'a_ru' => 'Да, читает 2D-коды, включая DataMatrix.', 'q_zh' => '能读取 Asl Belgisi 标识码吗？', 'a_zh' => '可以，读取二维码，包括 DataMatrix。'],
                ['q_uz' => 'HD versiyaning farqi nimada?', 'a_uz' => 'Maydaroq kodlarni o\'qiydi: Code 39 uchun 3 mil dan boshlab, standart SR versiyada esa 4 mil dan.', 'q_ru' => 'Чем отличается версия HD?', 'a_ru' => 'Читает более мелкие коды: для Code 39 от 3 mil, а стандартная версия SR — от 4 mil.', 'q_zh' => 'HD 版本有什么不同？', 'a_zh' => '可读取更小的条码：Code 39 从 3 mil 起，而标准 SR 版本从 4 mil 起。'],
                ['q_uz' => 'Qanday ulanadi?', 'a_uz' => 'USB orqali; RS-232 uchun alohida kabel kerak.', 'q_ru' => 'Как подключается?', 'a_ru' => 'По USB; для RS-232 нужен отдельный кабель.', 'q_zh' => '如何连接？', 'a_zh' => '通过 USB；RS-232 需要另配线缆。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'henex-3308-usb', 'action' => 'call',
            'title' => ['Henex H3308 simli 2D skaner, USB', 'Проводной 2D-сканер Henex H3308, USB', 'Henex H3308 有线 2D 扫描器，USB'],
            'description' => ['Qo\'lda ushlanadigan skaner: qog\'oz va ekrandagi 1D va 2D kodlarni o\'qiydi, megapikselli sensor (1280 × 800), kompyuterga USB kabel orqali ulanadi, drayver kerak emas; 2 m balandlikdan tushishga chidamli.', 'Ручной сканер: читает 1D- и 2D-коды с бумаги и экрана, мегапиксельный сенсор (1280 × 800), подключается к компьютеру USB-кабелем без драйверов; выдерживает падение с 2 м.', '手持式扫描器：可读取纸张和屏幕上的一维码和二维码，百万像素传感器（1280 × 800），通过 USB 线连接电脑，无需驱动；可承受 2 米跌落。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, skaner esa 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер читает 1D- и 2D-коды; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，本扫描器可读取一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Megapikselli sensor nima beradi?', 'a_uz' => '1280 × 800 sensor mayda va zich kodlarni o\'qishni osonlashtiradi.', 'q_ru' => 'Что даёт мегапиксельный сенсор?', 'a_ru' => 'Сенсор 1280 × 800 облегчает чтение мелких и плотных кодов.', 'q_zh' => '百万像素传感器有什么用？', 'a_zh' => '1280 × 800 传感器更容易读取小尺寸和高密度条码。'],
                ['q_uz' => 'Qanday ulanadi?', 'a_uz' => 'USB orqali; RS-232 va virtual COM-port rejimi ham bor.', 'q_ru' => 'Как подключается?', 'a_ru' => 'По USB; есть также RS-232 и режим виртуального COM-порта.', 'q_zh' => '如何连接？', 'a_zh' => '通过 USB；也支持 RS-232 和虚拟串口模式。'],
            ],
        ],
        [
            'category' => 'scanners', 'slug' => 'henex-hc-4208src', 'action' => 'call',
            'title' => ['Henex HC-4208SRC simsiz 2D skaner, zaryadlash tagligi bilan', 'Беспроводной 2D-сканер Henex HC-4208SRC с зарядной подставкой', 'Henex HC-4208SRC 无线 2D 扫描器，带充电底座'],
            'description' => ['Qog\'oz va ekrandagi 1D va 2D kodlarni o\'qiydi; megapikselli sensor, uch xil ulanish: taglik, Bluetooth yoki Type-C kabel.', 'Читает 1D- и 2D-коды с бумаги и экрана; мегапиксельный сенсор, три способа подключения: подставка, Bluetooth или кабель Type-C.', '可读取纸张和屏幕上的一维码和二维码；百万像素传感器，三种连接方式：底座、蓝牙或 Type-C 线。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, skaner esa 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер читает 1D- и 2D-коды; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，本扫描器可读取一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Aloqa bo\'lmasa nima bo\'ladi?', 'a_uz' => 'Skaner kodlarni xotirasida saqlaydi: 380 000 belgigacha, keyin ularni uzatadi.', 'q_ru' => 'Что будет, если связи нет?', 'a_ru' => 'Сканер сохраняет коды в памяти: до 380 000 символов, затем передаёт их.', 'q_zh' => '没有连接时怎么办？', 'a_zh' => '扫描器把条码存入内存：最多 380 000 个字符，之后再传输。'],
                ['q_uz' => 'Batareyasi almashtiriladimi?', 'a_uz' => 'Ha, 18650 turidagi batareya tez almashtiriladi.', 'q_ru' => 'Батарея заменяется?', 'a_ru' => 'Да, батарея типа 18650 быстро заменяется.', 'q_zh' => '电池可以更换吗？', 'a_zh' => '可以，18650 电池可快速更换。'],
            ],
        ],
        // pda
        [
            'category' => 'pda', 'slug' => 'meferi-me61', 'action' => 'call',
            'title' => ['Meferi ME61 ma\'lumot yig\'ish terminali, Android 15', 'Терминал сбора данных Meferi ME61, Android 15', 'Meferi ME61 数据采集终端，Android 15'],
            'description' => ['O\'rnatilgan 1D/2D skanerli Android terminal: 6 dyuymli ekran (1080 × 2160), xotira 4 + 64 GB, 5000 mAh batareya, himoya darajasi IP67; 4G, Wi-Fi va Bluetooth orqali ishlaydi.', 'Android-терминал со встроенным сканером 1D/2D: экран 6 дюймов (1080 × 2160), память 4 + 64 ГБ, батарея 5000 мА·ч, степень защиты IP67; работает по 4G, Wi-Fi и Bluetooth.', '内置一维/二维扫描器的 Android 终端：6 英寸屏幕（1080 × 2160），内存 4 + 64 GB，5000 mAh 电池，防护等级 IP67；支持 4G、Wi-Fi 和蓝牙。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, terminal skaneri esa 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер терминала читает 1D- и 2D-коды; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，终端的扫描器可读取一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Markor bilan qanday ishlaydi?', 'a_uz' => 'Markor veb-ilova bo\'lgani uchun terminal brauzerida Wi-Fi orqali ochiladi.', 'q_ru' => 'Как работает с Markor?', 'a_ru' => 'Markor — веб-приложение, поэтому открывается в браузере терминала по Wi-Fi.', 'q_zh' => '如何配合 Markor 使用？', 'a_zh' => 'Markor 是网页应用，因此可通过 Wi-Fi 在终端浏览器中打开。'],
                ['q_uz' => 'Batareyani ish vaqtida almashtirsa bo\'ladimi?', 'a_uz' => 'Ha, batareya terminalni o\'chirmasdan almashtiriladi.', 'q_ru' => 'Можно менять батарею во время работы?', 'a_ru' => 'Да, батарея меняется без выключения терминала.', 'q_zh' => '工作中可以更换电池吗？', 'a_zh' => '可以，无需关机即可更换电池。'],
            ],
        ],
        [
            'category' => 'pda', 'slug' => 'urovo-dt50', 'action' => 'call',
            'title' => ['Urovo DT50 ma\'lumot yig\'ish terminali, Android 13', 'Терминал сбора данных Urovo DT50, Android 13', 'Urovo DT50 数据采集终端，Android 13'],
            'description' => ['O\'rnatilgan 1D/2D skanerli Android terminal: 5,7 dyuymli ekran (720 × 1440), xotira 4 + 64 GB, 5000 mAh batareya, himoya darajasi IP67, 1,5 m balandlikdan tushishga chidamli.', 'Android-терминал со встроенным сканером 1D/2D: экран 5,7 дюйма (720 × 1440), память 4 + 64 ГБ, батарея 5000 мА·ч, степень защиты IP67, выдерживает падение с 1,5 м.', '内置一维/二维扫描器的 Android 终端：5.7 英寸屏幕（720 × 1440），内存 4 + 64 GB，5000 mAh 电池，防护等级 IP67，可承受 1.5 米跌落。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, terminal skaneri esa xalqaro standartdagi 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер терминала читает 1D- и 2D-коды международных стандартов; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，终端的扫描器可读取国际标准的一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Markor bilan qanday ishlaydi?', 'a_uz' => 'Markor veb-ilova bo\'lgani uchun terminal brauzerida Wi-Fi orqali ochiladi.', 'q_ru' => 'Как работает с Markor?', 'a_ru' => 'Markor — веб-приложение, поэтому открывается в браузере терминала по Wi-Fi.', 'q_zh' => '如何配合 Markor 使用？', 'a_zh' => 'Markor 是网页应用，因此可通过 Wi-Fi 在终端浏览器中打开。'],
                ['q_uz' => 'Xotirani kengaytirsa bo\'ladimi?', 'a_uz' => 'Ha, 256 GB gacha Micro SD karta qo\'yiladi.', 'q_ru' => 'Можно расширить память?', 'a_ru' => 'Да, ставится карта Micro SD до 256 ГБ.', 'q_zh' => '可以扩展存储吗？', 'a_zh' => '可以，支持最大 256 GB 的 Micro SD 卡。'],
            ],
        ],
        [
            'category' => 'pda', 'slug' => 'zebra-tc22', 'action' => 'call',
            'title' => ['Zebra TC22 ma\'lumot yig\'ish terminali, Android, Wi-Fi', 'Терминал сбора данных Zebra TC22, Android, Wi-Fi', 'Zebra TC22 数据采集终端，Android，Wi-Fi'],
            'description' => ['O\'rnatilgan 1D/2D skanerli Android terminal: 6 dyuymli ekran (1080 × 2160), xotira 6 + 64 yoki 8 + 128 GB, himoya darajasi IP68 va IP65; Wi-Fi 6E va Bluetooth 5.2 orqali ishlaydi, SIM-kartasiz.', 'Android-терминал со встроенным сканером 1D/2D: экран 6 дюймов (1080 × 2160), память 6 + 64 или 8 + 128 ГБ, степень защиты IP68 и IP65; работает по Wi-Fi 6E и Bluetooth 5.2, без SIM-карты.', '内置一维/二维扫描器的 Android 终端：6 英寸屏幕（1080 × 2160），内存 6 + 64 或 8 + 128 GB，防护等级 IP68 和 IP65；支持 Wi-Fi 6E 和蓝牙 5.2，不带 SIM 卡。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, terminal skaneri esa 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер терминала читает 1D- и 2D-коды; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，终端的扫描器可读取一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Markor bilan qanday ishlaydi?', 'a_uz' => 'Markor veb-ilova bo\'lgani uchun terminal brauzerida Wi-Fi orqali ochiladi.', 'q_ru' => 'Как работает с Markor?', 'a_ru' => 'Markor — веб-приложение, поэтому открывается в браузере терминала по Wi-Fi.', 'q_zh' => '如何配合 Markor 使用？', 'a_zh' => 'Markor 是网页应用，因此可通过 Wi-Fi 在终端浏览器中打开。'],
                ['q_uz' => 'Batareyasi qanday?', 'a_uz' => 'Almashtiriladigan batareya: 3800 mAh yoki kattalashtirilgan 5200 mAh.', 'q_ru' => 'Какая батарея?', 'a_ru' => 'Съёмная батарея: 3800 мА·ч или увеличенная 5200 мА·ч.', 'q_zh' => '电池如何？', 'a_zh' => '可拆卸电池：3800 mAh 或加大容量 5200 mAh。'],
            ],
        ],
        [
            'category' => 'pda', 'slug' => 'seuic-autoid-3p', 'action' => 'call',
            'title' => ['Seuic AUTOID 3P ma\'lumot yig\'ish terminali, Android 12', 'Терминал сбора данных Seuic AUTOID 3P, Android 12', 'Seuic AUTOID 3P 数据采集终端，Android 12'],
            'description' => ['O\'rnatilgan shtrix-kod skanerli Android terminal: 4 dyuymli IPS ekran (480 × 800), sakkiz yadroli protsessor, almashtiriladigan 5200 mAh batareya, himoya darajasi IP65.', 'Android-терминал со встроенным сканером штрихкодов: IPS-экран 4 дюйма (480 × 800), восьмиядерный процессор, съёмная батарея 5200 мА·ч, степень защиты IP65.', '内置条码扫描器的 Android 终端：4 英寸 IPS 屏幕（480 × 800），八核处理器，可拆卸 5200 mAh 电池，防护等级 IP65。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida; terminalning skaner moduli va o\'z kodingizda sinab ko\'rish bo\'yicha bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix; по сканирующему модулю терминала и проверке на своём коде позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码；关于终端的扫描模块以及用您的码测试，请致电我们。'],
                ['q_uz' => 'Markor bilan qanday ishlaydi?', 'a_uz' => 'Markor veb-ilova bo\'lgani uchun terminal brauzerida Wi-Fi orqali ochiladi.', 'q_ru' => 'Как работает с Markor?', 'a_ru' => 'Markor — веб-приложение, поэтому открывается в браузере терминала по Wi-Fi.', 'q_zh' => '如何配合 Markor 使用？', 'a_zh' => 'Markor 是网页应用，因此可通过 Wi-Fi 在终端浏览器中打开。'],
                ['q_uz' => 'Batareya almashtiriladimi?', 'a_uz' => 'Ha, 5200 mAh batareya yechib almashtiriladi.', 'q_ru' => 'Батарея заменяется?', 'a_ru' => 'Да, батарея 5200 мА·ч съёмная.', 'q_zh' => '电池可以更换吗？', 'a_zh' => '可以，5200 mAh 电池可拆卸更换。'],
            ],
        ],
        [
            'category' => 'pda', 'slug' => 'zebra-mc3300ax', 'action' => 'call',
            'title' => ['Zebra MC3300ax ma\'lumot yig\'ish terminali, to\'pponcha dastali, Android', 'Терминал сбора данных Zebra MC3300ax с пистолетной рукояткой, Android', 'Zebra MC3300ax 数据采集终端，枪式手柄，Android'],
            'description' => ['Klaviaturali va sensorli ekranli ombor terminali: 4 dyuymli ekran (800 × 480), o\'rnatilgan 1D/2D skaner, 7000 mAh batareya, xotira 4 + 32 GB, himoya darajasi IP64; Wi-Fi 6 va Bluetooth 5.1 orqali ishlaydi.', 'Складской терминал с клавиатурой и сенсорным экраном: экран 4 дюйма (800 × 480), встроенный сканер 1D/2D, батарея 7000 мА·ч, память 4 + 32 ГБ, степень защиты IP64; работает по Wi-Fi 6 и Bluetooth 5.1.', '带键盘和触摸屏的仓储终端：4 英寸屏幕（800 × 480），内置一维/二维扫描器，7000 mAh 电池，内存 4 + 32 GB，防护等级 IP64；支持 Wi-Fi 6 和蓝牙 5.1。'],
            'faq' => [
                ['q_uz' => 'Asl Belgisi kodlari uchun mos keladimi?', 'a_uz' => 'Asl Belgisi kodlari 2D DataMatrix formatida, terminal skaneri esa 1D va 2D kodlarni o\'qiydi; o\'z kodingizda sinab ko\'rish uchun bizga qo\'ng\'iroq qiling.', 'q_ru' => 'Подходит для кодов Asl Belgisi?', 'a_ru' => 'Коды Asl Belgisi — это 2D-коды DataMatrix, а сканер терминала читает 1D- и 2D-коды; чтобы проверить на своём коде, позвоните нам.', 'q_zh' => '适用于 Asl Belgisi 标识码吗？', 'a_zh' => 'Asl Belgisi 标识码为二维 DataMatrix 码，终端的扫描器可读取一维码和二维码；如需用您的码测试，请致电我们。'],
                ['q_uz' => 'Markor bilan qanday ishlaydi?', 'a_uz' => 'Markor veb-ilova bo\'lgani uchun terminal brauzerida Wi-Fi orqali ochiladi.', 'q_ru' => 'Как работает с Markor?', 'a_ru' => 'Markor — веб-приложение, поэтому открывается в браузере терминала по Wi-Fi.', 'q_zh' => '如何配合 Markor 使用？', 'a_zh' => 'Markor 是网页应用，因此可通过 Wi-Fi 在终端浏览器中打开。'],
                ['q_uz' => 'Qanchalik chidamli?', 'a_uz' => 'Betonga 1,8 m balandlikdan tushishga chidaydi.', 'q_ru' => 'Насколько прочный?', 'a_ru' => 'Выдерживает падение на бетон с высоты 1,8 м.', 'q_zh' => '有多耐用？', 'a_zh' => '可承受从 1.8 米高处跌落到混凝土地面。'],
            ],
        ],
        // ribbons
        [
            'category' => 'ribbons', 'slug' => 'ribbon-wax-60x300', 'action' => 'call',
            'title' => ['Wax ribbon 60 mm × 300 m', 'Риббон Wax 60 мм × 300 м', '蜡基碳带 60 mm × 300 m'],
            'description' => ['Oddiy va yarim yaltiroq qog\'oz etiketkalar uchun, katta hajmdagi chop etishga tejamkor; kengligi 60 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для обычных и полуглянцевых бумажных этикеток, экономичен при больших объёмах печати; ширина 60 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于普通和半光纸质标签，大批量打印更经济；宽 60 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 60 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 60 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 60 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Oddiy va yarim yaltiroq qog\'oz etiketkalar uchun; ishqalanishga chidamlilik kerak bo\'lsa, Wax/Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для обычных и полуглянцевых бумажных этикеток; если нужна стойкость к истиранию, берите Wax/Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于普通和半光纸质标签；如需耐摩擦，请选混合基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 55 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 55 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 55 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
        [
            'category' => 'ribbons', 'slug' => 'ribbon-wax-110x300', 'action' => 'call',
            'title' => ['Wax ribbon 110 mm × 300 m', 'Риббон Wax 110 мм × 300 м', '蜡基碳带 110 mm × 300 m'],
            'description' => ['Oddiy va yarim yaltiroq qog\'oz etiketkalar uchun, katta hajmdagi chop etishga tejamkor; kengligi 110 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для обычных и полуглянцевых бумажных этикеток, экономичен при больших объёмах печати; ширина 110 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于普通和半光纸质标签，大批量打印更经济；宽 110 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 110 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 110 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 110 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Oddiy va yarim yaltiroq qog\'oz etiketkalar uchun; ishqalanishga chidamlilik kerak bo\'lsa, Wax/Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для обычных и полуглянцевых бумажных этикеток; если нужна стойкость к истиранию, берите Wax/Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于普通和半光纸质标签；如需耐摩擦，请选混合基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 105 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 105 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 105 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
        [
            'category' => 'ribbons', 'slug' => 'ribbon-wax-resin-60x300', 'action' => 'call',
            'title' => ['Wax/Resin ribbon 60 mm × 300 m', 'Риббон Wax/Resin 60 мм × 300 м', '混合基碳带 60 mm × 300 m'],
            'description' => ['Yarim yaltiroq va qoplamali qog\'oz etiketkalar uchun, bosma ishqalanishga chidamliroq; kengligi 60 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для полуглянцевых и мелованных бумажных этикеток, печать более стойкая к истиранию; ширина 60 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于半光和铜版纸标签，打印内容更耐摩擦；宽 60 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 60 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 60 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 60 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Yarim yaltiroq va qoplamali qog\'oz etiketkalar uchun; sintetik etiketkalar uchun Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для полуглянцевых и мелованных бумажных этикеток; для синтетических этикеток берите Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于半光和铜版纸标签；合成材料标签请选树脂基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 55 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 55 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 55 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
        [
            'category' => 'ribbons', 'slug' => 'ribbon-wax-resin-110x300', 'action' => 'call',
            'title' => ['Wax/Resin ribbon 110 mm × 300 m', 'Риббон Wax/Resin 110 мм × 300 м', '混合基碳带 110 mm × 300 m'],
            'description' => ['Yarim yaltiroq va qoplamali qog\'oz etiketkalar uchun, bosma ishqalanishga chidamliroq; kengligi 110 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для полуглянцевых и мелованных бумажных этикеток, печать более стойкая к истиранию; ширина 110 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于半光和铜版纸标签，打印内容更耐摩擦；宽 110 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 110 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 110 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 110 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Yarim yaltiroq va qoplamali qog\'oz etiketkalar uchun; sintetik etiketkalar uchun Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для полуглянцевых и мелованных бумажных этикеток; для синтетических этикеток берите Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于半光和铜版纸标签；合成材料标签请选树脂基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 105 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 105 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 105 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
        [
            'category' => 'ribbons', 'slug' => 'ribbon-resin-60x300', 'action' => 'call',
            'title' => ['Resin ribbon 60 mm × 300 m', 'Риббон Resin 60 мм × 300 м', '树脂基碳带 60 mm × 300 m'],
            'description' => ['Sintetik etiketkalar uchun; bosma namlik, ishqalanish va kimyoviy moddalarga chidamli; kengligi 60 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для синтетических этикеток; печать устойчива к влаге, истиранию и химии; ширина 60 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于合成材料标签；打印内容耐潮湿、耐摩擦、耐化学品；宽 60 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 60 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 60 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 60 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Polipropilen va poliester etiketkalar uchun; qog\'oz etiketkalar uchun Wax yoki Wax/Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для полипропиленовых и полиэстеровых этикеток; для бумажных этикеток берите Wax или Wax/Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于聚丙烯和聚酯标签；纸质标签请选蜡基或混合基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 55 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 55 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 55 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
        [
            'category' => 'ribbons', 'slug' => 'ribbon-resin-110x300', 'action' => 'call',
            'title' => ['Resin ribbon 110 mm × 300 m', 'Риббон Resin 110 мм × 300 м', '树脂基碳带 110 mm × 300 m'],
            'description' => ['Sintetik etiketkalar uchun; bosma namlik, ishqalanish va kimyoviy moddalarga chidamli; kengligi 110 mm, uzunligi 300 m, o\'zagi 1 dyuym (25,4 mm), bo\'yoq tashqi tomonda.', 'Для синтетических этикеток; печать устойчива к влаге, истиранию и химии; ширина 110 мм, длина 300 м, втулка 1 дюйм (25,4 мм), красящий слой снаружи.', '适用于合成材料标签；打印内容耐潮湿、耐摩擦、耐化学品；宽 110 毫米，长 300 米，轴芯 1 英寸（25.4 毫米），外碳。'],
            'faq' => [
                ['q_uz' => 'Qaysi printerlarga mos?', 'a_uz' => '1 dyuymli o\'zakli, bo\'yog\'i tashqi tomonda bo\'lgan 300 m ribbon sig\'adigan va kamida 110 mm kenglikdagi ribbonni qabul qiladigan termotransfer printerlarga.', 'q_ru' => 'Для каких принтеров?', 'a_ru' => 'Для термотрансферных принтеров, в которые встаёт риббон 300 м на втулке 1 дюйм с красящим слоем снаружи и шириной не менее 110 мм.', 'q_zh' => '适用于哪些打印机？', 'a_zh' => '可装 1 英寸轴芯、外碳、300 米碳带且可用碳带宽度不小于 110 毫米的热转印打印机。'],
                ['q_uz' => 'Qaysi etiketkalar uchun?', 'a_uz' => 'Polipropilen va poliester etiketkalar uchun; qog\'oz etiketkalar uchun Wax yoki Wax/Resin oling.', 'q_ru' => 'Для каких этикеток?', 'a_ru' => 'Для полипропиленовых и полиэстеровых этикеток; для бумажных этикеток берите Wax или Wax/Resin.', 'q_zh' => '适用于哪些标签？', 'a_zh' => '适用于聚丙烯和聚酯标签；纸质标签请选蜡基或混合基碳带。'],
                ['q_uz' => 'Qaysi kenglikdagi etiketkaga mos?', 'a_uz' => 'Kengligi 105 mm gacha bo\'lgan etiketkalarga: ribbon etiketkadan biroz keng bo\'lishi kerak.', 'q_ru' => 'На какую ширину этикетки?', 'a_ru' => 'На этикетки шириной до 105 мм: риббон должен быть немного шире этикетки.', 'q_zh' => '适合多宽的标签？', 'a_zh' => '最宽 105 毫米的标签：碳带应比标签略宽。'],
            ],
        ],
    ];

    public function run(): void
    {
        $created = [];
        $order = [];
        foreach (self::PRODUCTS as $p) {
            $category = OfferCategory::where('slug', $p['category'])->first();
            if (!$category) {
                $this->command?->warn("Category '{$p['category']}' not found — run OfferSeeder first; {$p['slug']} skipped");
                continue;
            }
            $values = [
                'title_uz' => $p['title'][0], 'title_ru' => $p['title'][1], 'title_zh' => $p['title'][2],
                'description_uz' => $p['description'][0], 'description_ru' => $p['description'][1], 'description_zh' => $p['description'][2],
                'faq' => $p['faq'] ?: null,
            ];

            $order[$p['category']] = ($order[$p['category']] ?? -1) + 1; // position inside its category, as listed here
            $offer = Offer::where('slug', $p['slug'])->first();
            if ($offer) {
                // already there: fill only what is empty (admin edits win)
                $offer->fill(collect($values)->filter(fn ($v, $k) => filled($v) && blank($offer->$k))->all())->save();
                continue;
            }
            Offer::create($values + [
                'slug' => $p['slug'], 'offer_category_id' => $category->id, 'category' => $category->slug, 'icon' => $category->icon,
                'action' => $p['action'], 'sort_order' => $order[$p['category']], 'is_active' => true,
            ]);
            $created[] = $p['slug'];
        }

        // only on the run that adds real products: a placeholder the admin showed again stays visible
        $hidden = $created ? Offer::whereIn('slug', self::PLACEHOLDERS)->where('is_active', true)->update(['is_active' => false]) : 0;
        $this->command?->info('Products added: ' . count($created) . ', placeholders hidden: ' . $hidden);

        PageCache::bump();
    }
}
