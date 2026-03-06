<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('marks', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained();
            $table->foreignUuid('partner_id')->nullable()
                ->constrained();
            $table->foreignUuid('order_id')->nullable()
                ->constrained();
            $table->foreignUuid('item_id')->nullable()
                ->constrained();
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignUuid('operator_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status')->index()->default('pending');;
            $table->boolean('is_aggregation')->default(false);
            $table->string('aggregation_id')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('order_id');
            $table->index('is_aggregation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marks');
    }
};
