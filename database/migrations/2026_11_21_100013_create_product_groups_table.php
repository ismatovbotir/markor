<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asl Belgisi product categories with the DataMatrix structure of their marking codes.
 * structure: ordered segments [{ai, label, length, variable}], `ai` "" = raw tail without an AI,
 * `variable` = a GS separator follows the value when another field comes after it.
 * code_length / serial_length / crypto_length are derived from the structure (ProductGroup::saving).
 * The parser tries active categories in sort_order; codes remember the category they matched.
 *
 * The categories themselves come from Database\Seeders\ProductGroupSeeder (dev and prod), matched
 * by `slug`, so re-running the seeder adds new categories without touching the owner's edits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->nullable()->unique(); // stable key for the seeder (null = created by the owner)
            $table->string('name_uz');
            $table->string('name_ru')->nullable();
            $table->string('name_zh')->nullable();
            $table->string('code_format', 20)->default('custom'); // tobacco_short / standard_long / custom (informational)
            $table->json('structure')->nullable();
            $table->unsignedSmallInteger('code_length')->nullable();
            $table->text('sample_code')->nullable();
            $table->unsignedSmallInteger('serial_length')->default(0);
            $table->unsignedSmallInteger('crypto_length')->default(0);
            $table->boolean('requires_eds')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
