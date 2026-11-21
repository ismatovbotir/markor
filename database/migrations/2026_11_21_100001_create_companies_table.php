<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client companies. A registered client creates the company right after sign-up (tax number
 * first, details may come from the lookup API). All client data (batches, codes) is scoped by
 * company_id; a company has several users (users.company_id / company_role).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('tin', 14)->unique();          // STIR (9 digits) or PINFL of an entrepreneur (14 digits)
            $table->string('name');
            $table->string('legal_address')->nullable();
            $table->string('director')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('bank_mfo', 10)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('vat_code', 20)->nullable();
            $table->string('source', 10)->default('manual'); // manual / lookup
            $table->json('lookup_data')->nullable();          // raw answer of the lookup service, if any
            $table->boolean('is_active')->default(true);      // blocked by the super admin: none of its users can work
            // billing discount of the company in percent; null = config default. discount_until: last day it applies (null = no end)
            // free period: loading codes costs nothing until this day (inclusive), payment is due from the next day.
            // Set at registration (config billing.free_months), the super admin can prolong it.
            $table->date('free_until')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->date('discount_until')->nullable();
            // prepaid balance in so'm = confirmed payments − charged batches (kept in sync with balance_entries)
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('blocked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
