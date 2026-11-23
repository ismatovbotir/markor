<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marking systems besides Asl Belgisi (Честный ЗНАК, Russia; more countries later):
 *  - product_groups.marking_system — which system a category's codes belong to;
 *  - import_batches.marking_system — detected from the file's codes (or the company's usual one);
 *    marking_system_detected = false when the codes fit several systems (the client may switch);
 *  - companies.marking_system — the company's usual market, used when a file can't tell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_groups', function (Blueprint $table) {
            $table->string('marking_system', 32)->default('aslbelgisi')->after('slug')->index();
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('marking_system', 32)->default('aslbelgisi')->after('type');
            $table->boolean('marking_system_detected')->default(true)->after('marking_system');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->string('marking_system', 32)->nullable()->after('balance');
        });
    }

    public function down(): void
    {
        Schema::table('product_groups', fn (Blueprint $table) => $table->dropColumn('marking_system'));
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropColumn(['marking_system', 'marking_system_detected']));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('marking_system'));
    }
};
