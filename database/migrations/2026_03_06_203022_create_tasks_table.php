<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('order_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignUuid('partner_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'in_progress', 'done'])->default('todo');
            $table->foreignUuid('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index('company_id');
            $table->index('order_id');
            $table->index('partner_id');
            $table->index('item_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
