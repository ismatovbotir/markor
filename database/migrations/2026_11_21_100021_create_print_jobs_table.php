<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Print jobs of the prototype print flow (PrintJobService / RenderPrintJobJob). Batch PDFs are
 * rendered on the fly and do not create rows here. status: queued / rendering / ready / sent / failed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->constrained('label_templates')->restrictOnDelete();
            $table->foreignId('printer_id')->constrained('printers')->restrictOnDelete();
            $table->foreignId('created_by')->index()->constrained('users')->restrictOnDelete(); // client sees own jobs
            $table->json('code_ids');
            $table->foreignId('aggregation_id')->nullable()->constrained('aggregations')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('output_path')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
