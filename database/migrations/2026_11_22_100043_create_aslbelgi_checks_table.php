<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Asl Belgisi tekshiruvi" orders for a CSV batch: status + owner of every code via the read-only
 * OPEN API (POST cod/private/codes). The price is fixed on the order; the amount is what was finally
 * charged (0 when the check failed, only the checked codes on a partial run).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aslbelgi_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('codes')->default(0);                    // codes ordered
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);                    // charged (after refunds)
            $table->string('status', 20)->default('queued');                 // queued / running / done / partial / failed
            $table->unsignedInteger('checked')->default(0);
            $table->unsignedInteger('problems')->default(0);
            $table->string('error', 100)->nullable();                        // short reason key, never an API body
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aslbelgi_checks');
    }
};
