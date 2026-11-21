<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One uploaded CSV of marking codes (web or Telegram). The file waits in storage/app/imports until
 * the scheduler (batches:process) loads it.
 * status: uploaded (file saved, upload dialog open) → pending (queued) → processing (loading) → checking (classifier) → completed | failed
 * Billing is fixed at load time: unit_price, discount_percent, amount (to pay, after discount);
 * processed_rows = codes actually loaded = what is charged. Dashboards aggregate by
 * company / month of processed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->index()->constrained('users')->restrictOnDelete(); // admin usage per user
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();         // set when the file has a single GTIN
            $table->string('filename');
            $table->string('original_filename');
            $table->string('type', 20)->default('codes');     // codes / aggregation / mixed
            $table->string('source', 20)->default('web');     // web / telegram
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->json('error_log')->nullable();
            $table->timestamp('processed_at')->nullable();
            // prepayment: a loaded batch whose amount the balance did not cover is locked (no codes, no PDF) until paid
            $table->boolean('is_locked')->default(false);
            $table->timestamp('charged_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);                          // scheduler: oldest pending batch
            $table->index(['company_id', 'is_locked', 'id']);              // unlock the oldest locked batches after a payment
            $table->index(['status', 'processed_at']);                // super admin billing totals per month
            $table->index(['company_id', 'created_at']);              // client batch list (latest first), also covers the FK
            $table->index(['company_id', 'status', 'processed_at']);  // client / company billing per month
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
