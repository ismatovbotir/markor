<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Own visit counter for the public pages (Admin → Statistika). Daily aggregates only — no IP addresses:
 *  site_visits:   (date, kind, key) → count   kind = page | lang | ref | bot
 *  site_visitors: (date, hash)                one row per anonymous visitor per day (sha256 of ip+agent+day+app key)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('kind', 10);
            $table->string('key', 191);
            $table->unsignedInteger('count')->default(0);
            $table->unique(['date', 'kind', 'key']);
            $table->index(['kind', 'date']);
        });

        Schema::create('site_visitors', function (Blueprint $table) {
            $table->date('date');
            $table->char('hash', 64);
            $table->primary(['date', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visitors');
        Schema::dropIfExists('site_visits');
    }
};
