<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Printers: browser PDF or network thermal printers (direct TCP, phase 2). Managed in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);                                  // zpl_zebra / ezpl_godex / sbpl_bixolon / pdf_browser
            $table->string('connection_mode', 10)->default('browser');   // network / browser
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
