<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A banner can advertise a marketplace product: its texts and link are filled from the product,
 * and without an own picture the banner shows the product photo. Product removed → link cleared.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('url')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
        });
    }
};
