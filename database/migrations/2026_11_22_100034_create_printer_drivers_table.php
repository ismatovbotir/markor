<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Printer driver pages: no files are stored, each page links to the manufacturer's official download page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_drivers', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 60);
            $table->string('model', 80);
            $table->string('slug', 140)->unique();               // zebra-zd421 → /drivers/zebra-zd421
            $table->string('kind', 20)->default('desktop');      // desktop / industrial / mobile
            $table->string('language', 40)->nullable();          // printer command language: ZPL, EZPL, TSPL…
            $table->json('os')->nullable();                      // ["windows","macos","linux"]
            $table->string('driver_url', 500);                   // official download / support page (https)
            $table->string('summary_uz', 300)->nullable();
            $table->string('summary_ru', 300)->nullable();
            $table->string('summary_zh', 300)->nullable();
            $table->text('notes_uz')->nullable();                // extra install notes for this model (optional)
            $table->text('notes_ru')->nullable();
            $table->text('notes_zh')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'brand', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_drivers');
    }
};
