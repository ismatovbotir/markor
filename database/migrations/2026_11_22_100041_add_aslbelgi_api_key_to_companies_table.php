<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company's own Asl Belgisi (xTrace) business-user API key, entered by the company owner.
 * Stored encrypted (APP_KEY); used only for read-only API calls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('aslbelgi_api_key')->nullable();
            $table->timestamp('aslbelgi_api_key_expires_at')->nullable();
            $table->timestamp('aslbelgi_api_key_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['aslbelgi_api_key', 'aslbelgi_api_key_expires_at', 'aslbelgi_api_key_checked_at']);
        });
    }
};
