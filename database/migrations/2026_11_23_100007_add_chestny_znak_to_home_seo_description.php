<?php

use App\Domain\Settings\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Admin → SEO: the home page description now also names Честный ЗНАК (same texts as SettingsSeeder).
 * Replaces only the old seeded text (or an empty value) — a description the owner wrote himself stays as is.
 */
return new class extends Migration
{
    private const OLD = [
        'uz' => 'Exceldan etiketka yarating va printerda chop eting: QR, DataMatrix, EAN-13. Asl Belgisi kodlari — GS1 DataMatrix. Printer, ribbon, etiketka va servis.',
        'ru' => 'Этикетки из Excel с печатью на ваш принтер: QR, DataMatrix, EAN-13. Коды Asl Belgisi — корректный GS1 DataMatrix. Принтеры, риббоны, этикетки и сервис.',
        'zh' => '用Excel生成标签并直接打印：二维码、DataMatrix、EAN-13。Asl Belgisi标识码按GS1 DataMatrix编码。供应标签打印机、碳带和标签。',
    ];

    private const NEW = [
        'uz' => 'Exceldan etiketka yarating va printerda chop eting: QR, DataMatrix, EAN-13. Asl Belgisi va Chestniy ZNAK kodlari — GS1 DataMatrix. Printer, ribbon va etiketka.',
        'ru' => 'Этикетки из Excel с печатью на ваш принтер: QR, DataMatrix, EAN-13. Коды Asl Belgisi и Честный ЗНАК — корректный GS1 DataMatrix. Принтеры, риббоны, этикетки.',
        'zh' => '用Excel生成标签并直接打印：二维码、DataMatrix、EAN-13。Asl Belgisi与Chestny ZNAK码按GS1 DataMatrix编码。',
    ];

    public function up(): void
    {
        $this->swap(self::OLD, self::NEW, true);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD, false);
    }

    private function swap(array $from, array $to, bool $fillEmpty): void
    {
        $current = Setting::query()->pluck('value', 'key');
        $put = [];
        foreach ($to as $locale => $text) {
            $key = "seo.home.description.$locale";
            $value = $current[$key] ?? null;
            if ($value === $from[$locale] || ($fillEmpty && blank($value))) {
                $put[$key] = $text;
            }
        }
        if ($put) {
            Setting::put($put); // clears the settings cache and the cached home page
        }
    }
};
