<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Balance ledger of a company: + payment, − batch charge, ± refund/adjustment. companies.balance is the
 * running sum, updated in the same transaction; balance_after keeps the history readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                                                     // payment / charge / refund / adjustment
            $table->decimal('amount', 14, 2);                                               // signed
            $table->decimal('balance_after', 14, 2);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_entries');
    }
};
