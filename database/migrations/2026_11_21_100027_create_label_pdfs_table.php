<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generated label PDFs = the print log. One row per (batch, label design, part). Big batches are split into
 * parts of `label_templates.labels_per_pdf` labels (whole rows). The file itself is archived
 * in the super admin's Telegram (telegram_file_id); clients get it from there — instantly in the bot,
 * or streamed through the site. A local copy exists only until it is archived (or for a few days when
 * no Telegram archive is configured). A template edited after generation makes the PDF stale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_pdfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('part')->default(1);
            $table->unsignedSmallInteger('parts')->default(1);
            $table->unsignedInteger('first_label')->default(1);   // 1-based position of the first label in the batch
            $table->unsignedInteger('labels');
            $table->unsignedInteger('size_bytes');
            $table->string('local_path')->nullable();            // until archived / pruned
            $table->string('telegram_file_id')->nullable();
            $table->string('telegram_file_unique_id')->nullable();
            $table->string('telegram_chat_id')->nullable();      // archive chat it was sent to
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['import_batch_id', 'label_template_id', 'part']);
            $table->index(['company_id', 'created_at']);
            $table->index(['archived_at', 'created_at']); // pruning of local copies
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_pdfs');
    }
};
