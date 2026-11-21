<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outgoing notifications (Telegram / SMS) and their delivery status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);                    // telegram / sms
            $table->string('event');
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('pending'); // pending / sent / failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'channel']); // also covers the FK
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
