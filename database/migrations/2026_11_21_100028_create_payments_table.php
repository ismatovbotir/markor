<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Top-ups of a company's prepaid balance. A client reports a card transfer ("I paid") → pending;
 * the super admin confirms (amount may be corrected) or rejects. The super admin can also record
 * a payment directly (confirmed at once).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();       // who reported it
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('card');                                 // card / cash / bank
            $table->string('status', 20)->default('pending');                              // pending / confirmed / rejected
            $table->string('comment', 500)->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);   // admin queue of pending payments
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
