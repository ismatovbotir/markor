<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home page banners (slider at the top). The default banner (is_default) is the built-in hero:
 * always first, can't be deleted or switched off; its texts come from lang files (home.hero.*).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_default')->default(false);
            foreach (['uz', 'ru', 'zh'] as $l) {
                $table->string("title_$l", 160)->nullable();
                $table->string("text_$l", 400)->nullable();
                $table->string("button_$l", 60)->nullable();
            }
            $table->string('url', 500)->nullable();          // button link (site path or https://…)
            $table->boolean('is_sponsored')->default(false);  // a paid ad (brand / reseller): "Reklama" label, rel=sponsored
            $table->string('image_path')->nullable();
            $table->string('style', 10)->default('light');    // light / dark / amber
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
