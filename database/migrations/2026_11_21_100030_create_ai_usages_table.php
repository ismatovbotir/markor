<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per Claude API call: tokens, estimated cost, outcome — for the monthly counter and debugging. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();
            $table->string('feature', 40);                    // product_name / offer_content / check
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 10, 5)->default(0);
            $table->string('status', 20);                     // ok / refusal / credit / auth / error
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
