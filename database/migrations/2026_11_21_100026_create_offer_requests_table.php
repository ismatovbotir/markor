<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order requests for "Biz taklif qilamiz" items (or a label size), sent by visitors and clients.
 * The only table the public side writes to. status: new / done
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('label_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_title');
            $table->string('category')->nullable();
            $table->string('name', 120);
            $table->string('phone', 40);
            $table->string('quantity', 40)->nullable();
            $table->text('comment')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('status', 10)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']); // admin inbox, "new" badge
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_requests');
    }
};
