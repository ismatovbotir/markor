<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * origin: where the codes came from — csv (9 so'm/code) or aslbelgi (API import, 19 so'm/code).
 *   (source stays the upload channel: web / telegram)
 * charge_cancelled_at: the super admin cancelled the batch charge (money back to the balance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('origin', 20)->default('csv')->after('source');
            $table->timestamp('charge_cancelled_at')->nullable()->after('charged_at');
            $table->index(['origin']);
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn(['origin', 'charge_cancelled_at']);
        });
    }
};
