<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Label icons ("Знаки" menu of the label designer), managed by the super admin: add new, replace the image,
 * hide — never delete (saved templates refer to them by key).
 * Built-in icons: files in public/images/marks (Asl Belgisi + public-domain / CC0 files from Wikimedia Commons).
 * Честный ЗНАК has no file: it is CRPT's trademark, the owner uploads the official file himself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_marks', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('group', 20);
            $table->string('name_uz');
            $table->string('name_ru')->nullable();
            $table->string('name_zh')->nullable();
            $table->string('path')->nullable(); // relative to public/: images/marks/x.png or storage/label-marks/…
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('source', 500)->nullable(); // where the image comes from, licence
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $commons = fn (string $file, string $licence) => "Wikimedia Commons, File:$file — $licence";
        $rows = [
            ['aslbelgi', 'marking', 'Asl Belgisi belgisi', 'Знак Asl Belgisi', 'Asl Belgisi 标志', 'Asl Belgisi'],
            ['chestny_znak', 'marking', 'Chestniy ZNAK', 'Честный ЗНАК', 'Chestny ZNAK 标志',
                'Товарный знак ООО «Оператор-ЦРПТ» (св. 690729, 717580). Официальный файл — после регистрации на честныйзнак.рф/logo.consent; разрешено участникам оборота маркированных товаров, на территории РФ.'],
            ['eac', 'conformity', 'EAC (EOII)', 'EAC (ЕАЭС)', 'EAC（欧亚经济联盟）', $commons('EAC-black-on-white.svg', 'CC0')],
            ['rostest', 'conformity', 'РСТ (GOST R)', 'РСТ (ГОСТ Р)', 'РСТ（GOST R）', $commons('Знак_соответствия.svg', 'public domain')],
            ['ce', 'conformity', 'CE', 'CE', 'CE', $commons('Conformité_Européenne_(logo).svg', 'public domain')],
            ['fragile', 'handling', "Ehtiyot bo'ling, sinadi", 'Хрупкое', '易碎', $commons('ISO_7000_-_Ref-No_0621.svg', 'public domain')],
            ['this_way_up', 'handling', 'Yuqori tomoni', 'Верх', '向上', $commons('ISO_7000_-_Ref-No_0623.svg', 'CC0')],
            ['keep_dry', 'handling', 'Namlikdan saqlang', 'Беречь от влаги', '怕湿', $commons('ISO_7000_-_Ref-No_0626.svg', 'public domain')],
            ['no_sunlight', 'handling', 'Quyosh nuridan saqlang', 'Беречь от солнца', '避免日晒', $commons('ISO_7000_-_Ref-No_0624.svg', 'public domain')],
            ['temperature', 'handling', 'Harorat chegarasi', 'Ограничение температуры', '温度极限', $commons('ISO_7000_-_Ref-No_0632.svg', 'public domain')],
            ['no_hooks', 'handling', 'Ilgak ishlatmang', 'Крюками не брать', '禁用手钩', $commons('ISO_7000_-_Ref-No_0622.svg', 'CC0')],
            ['recycle', 'ecology', 'Qayta ishlash', 'Переработка', '可回收', $commons('Recycle001.svg', 'public domain')],
            ['weee', 'ecology', "Maishiy chiqindiga tashlamang", 'Не выбрасывать с бытовыми отходами', '勿作生活垃圾丢弃', $commons('WEEE_symbol_vectors.svg', 'public domain')],
            ['tidyman', 'ecology', 'Chiqindini qutiga tashlang', 'Выбросить в урну', '请丢入垃圾桶', $commons('International_tidyman.svg', 'public domain')],
            ['food_contact', 'info', 'Oziq-ovqat uchun', 'Для пищевых продуктов', '食品接触', $commons('EU_food_contact_material_symbol.svg', 'public domain')],
            ['estimated', 'info', '℮ — nominal miqdor', '℮ — номинальное количество', '℮ — 估计量', $commons('Estimated_sign.svg', 'public domain')],
            ['age_3', 'info', '3 yoshgacha mumkin emas', 'Не для детей до 3 лет', '不适合3岁以下儿童', $commons('Age_warning_symbol.svg', 'public domain')],
        ];

        $now = now();
        foreach ($rows as $i => [$key, $group, $uz, $ru, $zh, $source]) {
            $file = public_path("images/marks/$key.png");
            $size = is_file($file) ? @getimagesize($file) : false;
            DB::table('label_marks')->insertOrIgnore([
                'key' => $key, 'group' => $group, 'name_uz' => $uz, 'name_ru' => $ru, 'name_zh' => $zh,
                'path' => $size ? "images/marks/$key.png" : null, 'width' => $size[0] ?? null, 'height' => $size[1] ?? null,
                'source' => $source, 'sort' => ($i + 1) * 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('label_marks');
    }
};
