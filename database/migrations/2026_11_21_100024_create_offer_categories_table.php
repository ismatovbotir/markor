<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Biz taklif qilamiz": categories of what the owner sells. Each links to the owner's marketplace
 * shop (market_url). kind `templates` (the labels category) shows the label templates instead of
 * its own items. Content comes from OfferSeeder and is then edited in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('kind', 20)->default('items');   // items / templates
            $table->string('icon', 60)->nullable();          // heroicon name
            $table->string('name_uz', 120);
            $table->string('name_ru', 120)->nullable();
            $table->string('name_zh', 120)->nullable();
            $table->string('description_uz')->nullable();
            $table->string('description_ru')->nullable();
            $table->string('description_zh')->nullable();
            $table->string('market_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_categories');
    }
};
