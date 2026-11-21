<?php

namespace Database\Seeders;

use App\Domain\Settings\Setting;
use Illuminate\Database\Seeder;

/**
 * Initial values of Admin → Sozlamalar (dev and production).
 * Safe to run again (`php artisan db:seed --class=SettingsSeeder --force`): only empty keys are
 * filled — anything the super admin already changed on the page is never overwritten.
 */
class SettingsSeeder extends Seeder
{
    /** The owner's data. Empty = not set yet. */
    public const DATA = [
        'contact.phone'        => '+998 90 175 40 01',   // shown on "Call" buttons, e.g. +998 90 123 45 67
        'contact.telegram'     => 'posuz',   // Telegram username or channel, without @
        'contact.telegram_bot' => 'markor_uz_bot',   // bot username, without @
        'contact.wechat'       => 'BotirIsmatov',   // WeChat ID (Chinese clients)
        'contact.wechat_link'  => 'https://u.wechat.com/kG1sdLqQOGwNVC8caNoiLS8?s=2', // link inside the WeChat QR code
        'contact.email'        => 'info@pos.uz',   // order requests are sent here
        'billing.card_number'  => '9860600401849101',   // card clients transfer prepayments to (16 digits)
        'billing.card_holder'  => 'Ismatov B',   // name on the card
        // Admin → SEO: titles (≤60, Chinese ≤32) and descriptions (≤160, Chinese ≤85) of the public pages
        'seo.home.title.uz' => 'Etiketka chop etish va Asl Belgisi kodlari | Markor',
        'seo.home.title.ru' => 'Печать этикеток и кодов Asl Belgisi онлайн | Markor',
        'seo.home.title.zh' => '标签打印与Asl Belgisi标识码打印｜Markor',
        'seo.home.description.uz' => 'Exceldan etiketka yarating va printerda chop eting: QR, DataMatrix, EAN-13. Asl Belgisi kodlari — GS1 DataMatrix. Printer, ribbon, etiketka va servis.',
        'seo.home.description.ru' => 'Этикетки из Excel с печатью на ваш принтер: QR, DataMatrix, EAN-13. Коды Asl Belgisi — корректный GS1 DataMatrix. Принтеры, риббоны, этикетки и сервис.',
        'seo.home.description.zh' => '用Excel生成标签并直接打印：二维码、DataMatrix、EAN-13。Asl Belgisi标识码按GS1 DataMatrix编码。供应标签打印机、碳带和标签。',
        'seo.studio.title.uz' => 'Bepul etiketka dizayneri onlayn — Exceldan | Markor',
        'seo.studio.title.ru' => 'Конструктор этикеток онлайн бесплатно — из Excel | Markor',
        'seo.studio.title.zh' => '免费在线标签设计器｜Excel批量打印｜Markor',
        'seo.studio.description.uz' => 'Ro\'yxatdan o\'tmasdan etiketka yarating: Excel yoki CSV yuklang, QR, DataMatrix, EAN-13, Code 128 qo\'shing, printerda chop eting yoki PDF oling.',
        'seo.studio.description.ru' => 'Создайте этикетки без регистрации: загрузите Excel или CSV, добавьте QR, DataMatrix, EAN-13, Code 128 и печатайте на принтер или скачайте PDF.',
        'seo.studio.description.zh' => '无需注册即可设计标签：上传Excel或CSV，添加二维码、DataMatrix、EAN-13、Code 128，直接打印或下载PDF。',
        'seo.china.title.uz' => 'Xitoydan yetkazuvchilar uchun Asl Belgisi | Markor',
        'seo.china.title.ru' => 'Маркировка Asl Belgisi для поставщиков из Китая | Markor',
        'seo.china.title.zh' => '乌兹别克斯坦标识码打印｜Asl Belgisi｜Markor',
        'seo.china.description.uz' => 'Xitoydan O\'zbekistonga tovar yetkazasizmi? Asl Belgisi CSV faylini yuklang va FNC1, GS bilan GS1 DataMatrix etiketka PDF oling. Xitoy tilida interfeys.',
        'seo.china.description.ru' => 'Поставляете из Китая в Узбекистан? Загрузите CSV с кодами Asl Belgisi и получите PDF-этикетки GS1 DataMatrix с FNC1 и GS. Интерфейс на китайском.',
        'seo.china.description.zh' => '出口乌兹别克斯坦：上传Asl Belgisi标识码CSV，生成带FNC1和GS的GS1 DataMatrix标签PDF，可在国内工厂打印。中文界面，首月免费。',
        'seo.contact.title.uz' => 'Aloqa — etiketka printerlari va Asl Belgisi | Markor',
        'seo.contact.title.ru' => 'Контакты — принтеры этикеток и Asl Belgisi | Markor',
        'seo.contact.title.zh' => '联系我们｜标签打印机与Asl Belgisi｜Markor',
        'seo.contact.description.uz' => 'Markor bilan bog\'laning: telefon, Telegram, email. Etiketka printerlari, ribbon, etiketka, skaner va Asl Belgisi kodlarini chop etish bo\'yicha savollar.',
        'seo.contact.description.ru' => 'Свяжитесь с Markor: телефон, Telegram, email. Вопросы о принтерах этикеток, риббонах, этикетках, сканерах и печати кодов Asl Belgisi.',
        'seo.contact.description.zh' => '联系Markor：微信、电话、Telegram、电子邮件。咨询标签打印机、碳带、标签、扫描枪及Asl Belgisi标识码打印。',
        'seo.drivers.title.uz' => 'Printer drayverlari: Zebra, Godex, Xprinter | Markor',
        'seo.drivers.title.ru' => 'Драйверы принтеров этикеток Zebra, Godex, Xprinter | Markor',
        'seo.drivers.title.zh' => '标签打印机驱动｜Zebra、Godex、Xprinter',
        'seo.drivers.description.uz' => 'Zebra, Godex, Xprinter etiketka printerlari uchun drayverlar: rasmiy saytdan yuklab olish, Windows va macOS\'da o\'rnatish, kalibrlash va muammolar.',
        'seo.drivers.description.ru' => 'Драйверы для принтеров этикеток Zebra, Godex, Xprinter: скачать с официального сайта, установка в Windows и macOS, калибровка и частые проблемы.',
        'seo.drivers.description.zh' => 'Zebra、Godex、Xprinter 标签打印机驱动：从官网下载，在 Windows 和 macOS 上安装，校准及常见问题。',
        'seo.ai_agents'        => 'all',   // AI search and assistants may read the site
    ];

    public function run(): void
    {
        $current = Setting::query()->pluck('value', 'key');
        $fill = collect(self::DATA)
            ->filter(fn ($value, $key) => filled($value) && blank($current[$key] ?? null))
            ->all();

        if ($fill) {
            Setting::put($fill);
        }
        $this->command?->info('Settings filled: ' . ($fill ? implode(', ', array_keys($fill)) : 'nothing'));
    }
}
