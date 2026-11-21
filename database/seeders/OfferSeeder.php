<?php

namespace Database\Seeders;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use Illuminate\Database\Seeder;

/**
 * Starter content for "Biz taklif qilamiz". Categories and items are matched by slug, so re-running
 * updates them and nothing is deleted; after that, edit everything in the admin panel (Biz taklif qilamiz menu group).
 * Items are generic product types — replace them with the exact models you sell.
 */
class OfferSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['slug' => 'labels', 'kind' => 'templates', 'icon' => 'heroicon-o-tag',
             'name' => ['Etiketkalar', 'Этикетки', '标签'],
             'description' => ["Termo va termotransfer etiketkalar rulonda. Har bir shablon — sotiladigan o'lcham.", 'Термо- и термотрансферные этикетки в рулонах. Каждый шаблон — размер, который мы продаём.', '卷装热敏和热转印标签。每个模板就是我们销售的一个尺寸。'],
             'items' => []],

            ['slug' => 'ribbons', 'icon' => 'heroicon-o-film',
             'name' => ['Ribbonlar', 'Риббоны', '碳带'],
             'description' => ['Termotransfer printerlar uchun lentalar.', 'Ленты для термотрансферных принтеров.', '热转印打印机用碳带。'],
             'items' => [
                 ['ribbon-wax-110', ['Wax ribbon 110 mm × 300 m', 'Риббон Wax 110 мм × 300 м', '蜡基碳带 110 mm × 300 m'], ["Qog'oz etiketkalar uchun, eng tejamkor.", 'Для бумажных этикеток, самый экономичный.', '适用于纸质标签，最经济。']],
                 ['ribbon-wax-resin-110', ['Wax/Resin ribbon 110 mm × 300 m', 'Риббон Wax/Resin 110 мм × 300 м', '混合基碳带 110 mm × 300 m'], ['Yarim yaltiroq va qoplamali etiketkalar uchun, ishqalanishga chidamli.', 'Для полуглянцевых и мелованных этикеток, устойчив к истиранию.', '适用于半光和铜版标签，耐摩擦。']],
                 ['ribbon-resin-110', ['Resin ribbon 110 mm × 300 m', 'Риббон Resin 110 мм × 300 м', '树脂基碳带 110 mm × 300 m'], ['Sintetik etiketkalar uchun: namlik, issiqlik va kimyoviy moddalarga chidamli.', 'Для синтетических этикеток: стойкость к влаге, теплу и химии.', '适用于合成标签，耐潮、耐热、耐化学品。']],
                 ['ribbon-wax-60', ['Wax ribbon 60 mm × 300 m', 'Риббон Wax 60 мм × 300 м', '蜡基碳带 60 mm × 300 m'], ['Tor etiketkalar uchun (58–60 mm).', 'Для узких этикеток (58–60 мм).', '适用于窄标签（58–60 mm）。']],
             ]],

            ['slug' => 'printers', 'icon' => 'heroicon-o-printer',
             'name' => ['Printerlar', 'Принтеры', '打印机'],
             'description' => ['Etiketka va DataMatrix chop etish uchun printerlar, o\'rnatish va sozlash bilan.', 'Принтеры этикеток и DataMatrix, с установкой и настройкой.', '标签与 DataMatrix 打印机，含安装调试。'],
             'items' => [
                 ['printer-desktop-dt', ['Stol usti termo printer, 108 mm, 203 dpi', 'Настольный термопринтер, 108 мм, 203 dpi', '桌面热敏打印机，108 mm，203 dpi'], ['Kichik va o\'rta hajmlar uchun, ribbon kerak emas.', 'Для малых и средних объёмов, без риббона.', '适合中小批量，无需碳带。']],
                 ['printer-desktop-tt', ['Stol usti termotransfer printer, 203/300 dpi', 'Настольный термотрансферный принтер, 203/300 dpi', '桌面热转印打印机，203/300 dpi'], ['Uzoq saqlanadigan etiketkalar uchun; mayda DataMatrix uchun 300 dpi.', 'Для долговечных этикеток; 300 dpi для мелкого DataMatrix.', '适合长期保存的标签；小尺寸 DataMatrix 建议 300 dpi。']],
                 ['printer-industrial', ['Sanoat printeri, 300 dpi', 'Промышленный принтер, 300 dpi', '工业打印机，300 dpi'], ['Katta hajm va uzluksiz ish uchun, metall korpus.', 'Для больших объёмов и непрерывной работы, металлический корпус.', '适合大批量连续作业，金属机身。']],
                 ['printer-mobile', ['Mobil printer, Bluetooth', 'Мобильный принтер, Bluetooth', '便携打印机，蓝牙'], ['Omborda va yo\'lda etiketka chop etish uchun.', 'Для печати этикеток на складе и в пути.', '用于仓库和外勤打印标签。']],
             ]],

            ['slug' => 'scanners', 'icon' => 'heroicon-o-qr-code',
             'name' => ['Skanerlar', 'Сканеры', '扫描器'],
             'description' => ['DataMatrix va QR kodlarni o\'qiydigan 2D skanerlar.', '2D-сканеры для DataMatrix и QR.', '可识读 DataMatrix 和二维码的 2D 扫描器。'],
             'items' => [
                 ['scanner-wired-2d', ['Simli 2D skaner, USB', 'Проводной 2D-сканер, USB', '有线 2D 扫描器，USB'], ['Kassa va ish joyi uchun, GS1 DataMatrix ni to\'g\'ri o\'qiydi.', 'Для кассы и рабочего места, правильно читает GS1 DataMatrix.', '适用于收银台和工位，正确识读 GS1 DataMatrix。']],
                 ['scanner-wireless-2d', ['Simsiz 2D skaner', 'Беспроводной 2D-сканер', '无线 2D 扫描器'], ['Bluetooth yoki radio, ombor bo\'ylab erkin harakat.', 'Bluetooth или радио, свобода движения по складу.', '蓝牙或无线电，仓库内自由移动。']],
                 ['scanner-presentation', ['Stol usti (stasionar) skaner', 'Настольный (стационарный) сканер', '桌面固定式扫描器'], ['Qo\'lni bo\'shatadi: mahsulotni oynaga yaqinlashtirish kifoya.', 'Руки свободны: достаточно поднести товар.', '解放双手：只需将商品靠近即可。']],
                 ['scanner-industrial', ['Sanoat skaneri', 'Промышленный сканер', '工业扫描器'], ['Zarba va changga chidamli, ishlab chiqarish uchun.', 'Устойчив к ударам и пыли, для производства.', '抗冲击防尘，适用于生产线。']],
             ]],

            ['slug' => 'pda', 'icon' => 'heroicon-o-device-phone-mobile',
             'name' => ['PDA (TSD)', 'ТСД', '数据采集终端'],
             'description' => ['Ma\'lumot yig\'ish terminallari: inventarizatsiya, qabul va agregatsiya.', 'Терминалы сбора данных: инвентаризация, приёмка, агрегация.', '数据采集终端：盘点、收货、聚合。'],
             'items' => [
                 ['pda-android', ['Android TSD, 2D skaner bilan', 'ТСД на Android со 2D-сканером', '安卓数据终端，带 2D 扫描'], ['Omborda kodlarni skanerlash va tekshirish uchun.', 'Для сканирования и проверки кодов на складе.', '用于仓库扫码与核验。']],
                 ['pda-rugged', ['Mustahkam TSD, IP65', 'Защищённый ТСД, IP65', '三防数据终端，IP65'], ['Yiqilish, chang va namlikka chidamli.', 'Выдерживает падения, пыль и влагу.', '耐跌落、防尘防水。']],
                 ['pda-pistol', ['Pistol tutqichli TSD', 'ТСД с пистолетной рукояткой', '枪柄式数据终端'], ['Ko\'p skanerlash uchun qulay — kun bo\'yi ishlash.', 'Удобен для интенсивного сканирования весь день.', '适合全天高频扫描。']],
                 ['pda-cradle', ['TSD zaryadlash stansiyasi', 'Зарядная станция для ТСД', '数据终端充电座'], ['Bir nechta terminal va batareyani zaryadlash.', 'Зарядка нескольких терминалов и батарей.', '可同时为多台终端和电池充电。']],
             ]],

            ['slug' => 'accessories', 'icon' => 'heroicon-o-wrench',
             'name' => ['Aksessuarlar', 'Аксессуары', '配件'],
             'description' => ['Etiketkalar bilan ishlashni tezlashtiradigan qurilmalar.', 'Устройства, ускоряющие работу с этикетками.', '提升贴标效率的设备。'],
             'items' => [
                 ['label-rewinder', ['Etiketka o\'rovchi (rewinder)', 'Смотчик этикеток (ревайндер)', '标签回卷器'], ["Chop etilgan etiketkalarni rulonga qayta o'raydi.", 'Сматывает напечатанные этикетки обратно в рулон.', '将打印好的标签重新卷成卷。']],
                 ['label-applicator', ['Etiketka applikatori', 'Аппликатор этикеток', '贴标机'], ['Etiketkani mahsulotga tez va tekis yopishtiradi.', 'Быстро и ровно наклеивает этикетку на товар.', '快速、平整地将标签贴到商品上。']],
                 ['label-holder', ['Rulon ushlagich (label holder)', 'Держатель рулона этикеток', '标签卷支架'], ['Katta rulonlar uchun tashqi ushlagich.', 'Внешний держатель для больших рулонов.', '用于大卷标签的外置支架。']],
                 ['label-peeler', ['Etiketka ajratgich (peeler)', 'Отделитель этикеток (пилер)', '标签剥离器'], ['Etiketkani podlojkadan avtomatik ajratib beradi.', 'Автоматически отделяет этикетку от подложки.', '自动将标签与底纸分离。']],
             ]],

            ['slug' => 'service', 'icon' => 'heroicon-o-wrench-screwdriver',
             'name' => ['Xizmat', 'Сервис', '服务'],
             'description' => ['O\'rnatish, sozlash va texnik yordam.', 'Установка, настройка и техподдержка.', '安装、调试与技术支持。'],
             'items' => [
                 ['tech-support', ['Texnik yordam', 'Техническая поддержка', '技术支持'], ["Printer chop etmayaptimi yoki skaner kodni o'qimayaptimi? Qo'ng'iroq qiling — yordam beramiz.", 'Принтер не печатает или сканер не читает код? Позвоните — поможем.', '打印机不出纸或扫描器无法识别？请致电，我们来帮您。']],
             ]],
        ];

        foreach ($catalog as $order => $c) {
            $category = OfferCategory::updateOrCreate(['slug' => $c['slug']], [
                'kind' => $c['kind'] ?? 'items', 'icon' => $c['icon'],
                'name_uz' => $c['name'][0], 'name_ru' => $c['name'][1], 'name_zh' => $c['name'][2],
                'description_uz' => $c['description'][0], 'description_ru' => $c['description'][1], 'description_zh' => $c['description'][2],
                'sort_order' => $order,
            ]);

            foreach ($c['items'] as $i => [$slug, $title, $text]) {
                Offer::updateOrCreate(['slug' => $slug], [
                    'offer_category_id' => $category->id, 'category' => $c['slug'], 'icon' => $c['icon'],
                    'title_uz' => $title[0], 'title_ru' => $title[1], 'title_zh' => $title[2],
                    'description_uz' => $text[0], 'description_ru' => $text[1], 'description_zh' => $text[2],
                    'action' => $c['slug'] === 'service' ? 'call' : 'order', 'sort_order' => $i, 'is_active' => true,
                ]);
            }
        }

        // nothing is deleted: real products (MarketplaceProductsSeeder) and items added in admin stay
    }
}
