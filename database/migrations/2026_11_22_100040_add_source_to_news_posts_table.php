<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** News taken from another site: who published it first and the link to the original article. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->string('source_name', 120)->nullable()->after('body_zh');
            $table->string('source_url', 500)->nullable()->unique()->after('source_name');
        });
    }

    public function down(): void
    {
        Schema::table('news_posts', function (Blueprint $table) {
            $table->dropUnique(['source_url']);
            $table->dropColumn(['source_name', 'source_url']);
        });
    }
};
