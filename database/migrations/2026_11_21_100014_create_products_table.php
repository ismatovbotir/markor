<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global product catalogue: exactly one row per GTIN, shared by all companies (ownership lives on
 * marking_codes.company_id). Created by the importer from the GTIN alone; name etc. are filled in
 * later by the classifier lookup (tasnif.soliq.uz) or by hand.
 * attributes (JSON): classifier details (mxik, classifier_name, units, aslbelgi flags, …).
 * lookup_status / lookup_at: result of the last classifier lookup as real columns, so the scheduler
 * can find products to (re)check with an index instead of a JSON scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('gtin', 14)->unique();
            $table->string('name_uz')->nullable();
            $table->string('name_ru')->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('barcode_ean', 13)->nullable();
            $table->json('attributes')->nullable();
            $table->string('lookup_status', 12)->nullable();  // null (not checked) / found / not_found / error
            $table->timestamp('lookup_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lookup_status', 'lookup_at']); // scheduler: retry failed lookups
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
