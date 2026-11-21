<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool of SSCC numbers (imported from the operator or generated from the GS1 company prefix),
 * handed out to aggregations. Prototype feature, kept for phase 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sscc_pool', function (Blueprint $table) {
            $table->id();
            $table->string('sscc', 18)->unique();
            $table->string('sscc_source', 20);   // imported_pool / generated
            $table->string('gcp', 9);
            $table->string('serial', 8);
            $table->string('check_digit', 1);
            $table->boolean('is_used')->default(false);
            $table->foreignId('aggregation_id')->nullable()->constrained('aggregations')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_used', 'sscc_source']); // next free SSCC
            $table->index('gcp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sscc_pool');
    }
};
