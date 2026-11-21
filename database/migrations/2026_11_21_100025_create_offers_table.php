<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Biz taklif qilamiz" items (equipment, supplies, service), shown on the public and client pages.
 * `category` duplicates the category slug (legacy, still written by the seeder / admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('category');                 // category slug
            $table->string('icon', 60)->nullable();
            $table->string('image_path')->nullable();
            $table->string('title_uz', 160);
            $table->string('title_ru', 160)->nullable();
            $table->string('title_zh', 160)->nullable();
            $table->text('description_uz')->nullable();
            $table->text('description_ru')->nullable();
            $table->text('description_zh')->nullable();
            $table->string('price', 60)->nullable();     // free text: "kelishiladi", "1 200 000 so'm"
            $table->string('url', 500)->nullable();      // the item on the marketplace
            $table->string('action', 10)->default('call'); // call / order
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['offer_category_id', 'is_active', 'sort_order']); // items of a category, in order
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
