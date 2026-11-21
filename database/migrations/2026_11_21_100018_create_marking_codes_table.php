<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asl Belgisi marking codes (GS1 DataMatrix, text form without FNC1/GS). The biggest and hottest
 * table: bulk-inserted by the importer in chunks of 1000 with insertOrIgnore (code is globally
 * unique, duplicates are skipped), read per batch for PDF rendering, listed per company.
 * Owned by a company (company_id, global scope `owner`); user_id = who uploaded it.
 * status: imported → assigned → printed → aggregated → shipped | cancelled
 * Every foreign key is nullOnDelete: codes outlive the batch, product, user or company row.
 *
 * Lengths: a category segment is at most 90 chars and a structure has at most 12 segments, so
 * code / crypto_tail get generous limits (PostgreSQL enforces varchar lengths, SQLite does not).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marking_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 500)->unique();
            $table->string('gtin', 14);
            $table->string('serial', 90);
            $table->string('crypto_tail', 500)->nullable();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('aggregation_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('imported');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('aggregated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // codes of a batch: count, distinct products, PDF rendering (also covers the FK)
            $table->index(['import_batch_id', 'product_id']);
            // client code list, newest first; with a status filter (also cover the company FK)
            $table->index(['company_id', 'id']);
            $table->index(['company_id', 'status', 'id']);
            // product visibility (EXISTS … product_id, company_id), codes count per product,
            // client list filtered by product, newest first
            $table->index(['product_id', 'company_id', 'id']);
            // lookup by GTIN + serial (scanner / API reconciliation)
            $table->index(['gtin', 'serial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marking_codes');
    }
};
