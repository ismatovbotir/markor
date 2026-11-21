<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aggregation units (SSCC): level 1 group pack … level 3 pallet, nested via parent_id.
 * Prototype feature (AggregationService / AggregationScanner), kept for phase 2.
 * status: open / closed / disaggregated
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aggregations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('sscc', 18)->unique();
            $table->string('sscc_source', 20)->default('generated'); // imported_pool / generated
            $table->unsignedTinyInteger('level')->default(1);         // 1 / 2 / 3
            $table->foreignId('parent_id')->nullable()->constrained('aggregations')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('actual_quantity')->default(0);
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'status']); // top-level list (parent_id null), children of a unit
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregations');
    }
};
