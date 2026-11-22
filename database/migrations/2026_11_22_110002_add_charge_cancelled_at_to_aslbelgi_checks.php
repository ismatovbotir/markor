<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The super admin cancelled the batch charge: this check's money went back too (never refunded twice). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aslbelgi_checks', function (Blueprint $table) {
            $table->timestamp('charge_cancelled_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('aslbelgi_checks', function (Blueprint $table) {
            $table->dropColumn('charge_cancelled_at');
        });
    }
};
