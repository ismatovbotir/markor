<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Names a client gives to a product the classifier did not know. They apply to THAT company's labels
 * only: products are shared by GTIN, so one company must never change what another company prints.
 * The shared name (products.name_*) comes only from the classifier or the super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_product_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name_uz')->nullable();
            $table->string('name_ru')->nullable();
            $table->string('sku', 100)->nullable();
            $table->foreignId('named_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_product_names');
    }
};
