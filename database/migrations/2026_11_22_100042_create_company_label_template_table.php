<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "My templates": the label templates a company chose to use; only these are offered for PDF / printing. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_label_template', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_template_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'label_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_label_template');
    }
};
