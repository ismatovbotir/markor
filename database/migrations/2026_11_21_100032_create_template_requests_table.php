<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A client's request ("ticket") for a new label template: size, labels per row / rows, comment. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedTinyInteger('columns')->default(1);   // labels side by side in a row
            $table->unsignedSmallInteger('rows')->nullable();       // rows on a sheet (sheet labels), empty = roll
            $table->decimal('gap_mm', 4, 1)->nullable();
            $table->text('comment')->nullable();
            $table->string('status', 10)->default('new');           // new / done / rejected
            $table->text('admin_note')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('label_templates')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_requests');
    }
};
