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
        Schema::create('orders', function (Blueprint $table) {

            $table->uuid('id')->primary();
            $table->string('status')->index()->default('new');
            $table->string('number')->nullable();
            $table->foreignUuid('company_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('partner_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('comment')->nullable();
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
