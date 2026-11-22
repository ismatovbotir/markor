<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result of the Asl Belgisi check per code (raw registry status + our problem reason; a code with a
 * problem is not printed), a revision counter on the batch that changes whenever its set of problem
 * codes changes (stored PDFs of an older revision are stale), and the last key-expiry reminder sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marking_codes', function (Blueprint $table) {
            $table->string('aslbelgi_status', 40)->nullable();
            $table->string('aslbelgi_problem', 30)->nullable();
            $table->timestamp('aslbelgi_checked_at')->nullable();
            $table->index(['import_batch_id', 'aslbelgi_problem']);
        });
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('aslbelgi_revision')->default(0);
        });
        Schema::table('label_pdfs', function (Blueprint $table) {
            $table->unsignedInteger('aslbelgi_revision')->default(0);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->string('aslbelgi_key_reminded', 40)->nullable(); // "<expiry date>:<threshold>" of the last reminder
        });
    }

    public function down(): void
    {
        Schema::table('marking_codes', function (Blueprint $table) {
            $table->dropIndex(['import_batch_id', 'aslbelgi_problem']);
            $table->dropColumn(['aslbelgi_status', 'aslbelgi_problem', 'aslbelgi_checked_at']);
        });
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropColumn('aslbelgi_revision'));
        Schema::table('label_pdfs', fn (Blueprint $table) => $table->dropColumn('aslbelgi_revision'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('aslbelgi_key_reminded'));
    }
};
