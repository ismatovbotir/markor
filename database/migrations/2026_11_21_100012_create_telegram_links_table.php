<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linking a client account to Telegram: QR (token) → the bot checks the shared phone →
 * the cabinet shows a 6-digit code → the client types it in the bot → linked (users.telegram_chat_id).
 * status: pending (QR shown) → started (bot opened) → phone_ok (code shown) → linked | phone_mismatch | failed | expired
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 40)->unique();
            $table->string('chat_id', 32)->nullable();
            $table->string('tg_username', 64)->nullable();   // who opened the bot
            $table->string('tg_name')->nullable();
            $table->string('shared_phone', 30)->nullable();  // the phone they shared (matched or not)
            $table->string('code', 6)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['chat_id', 'status']); // bot: open link of a chat
            $table->index(['user_id', 'status']); // cabinet: expire the user's open links (also covers the FK)
            $table->index(['status', 'updated_at']); // admin: unmatched / unfinished attempts
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_links');
    }
};
