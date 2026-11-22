<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company logo on labels (original + black-and-white print version, a version number that makes
 * stored batch PDFs stale when the logo changes) and the optional "markor.uz" mark on templates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
            $table->string('logo_print_path')->nullable();
            $table->unsignedInteger('logo_version')->default(0);
        });
        Schema::table('label_pdfs', function (Blueprint $table) {
            $table->unsignedInteger('logo_version')->default(0);
        });
        Schema::table('label_templates', function (Blueprint $table) {
            $table->boolean('watermark')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['logo_path', 'logo_print_path', 'logo_version']));
        Schema::table('label_pdfs', fn (Blueprint $table) => $table->dropColumn('logo_version'));
        Schema::table('label_templates', fn (Blueprint $table) => $table->dropColumn('watermark'));
    }
};
