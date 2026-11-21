<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts. role: `admin` (super admin, the service owner) or `user` (registered client).
 * A client belongs to a company (company_role owner / member).
 * billing discount, null = default from config/billing.php. Soft-deleted, so rows referencing a
 * user (batches, codes) stay intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('company_role', 10)->nullable();   // owner / member
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 10)->default('user');      // admin / user
            $table->string('locale', 5)->default('ru');       // uz / ru / zh
            $table->string('qr_token', 64)->nullable()->unique();
            $table->string('telegram_chat_id', 32)->nullable()->index(); // bot looks users up by chat
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
