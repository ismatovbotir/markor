<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver no longer needs one printer model: one package often fits several models, which are now the
 * linked marketplace products (offer_printer_driver). `model` stays as an optional driver name ("ZDesigner").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printer_drivers', function (Blueprint $table) {
            $table->string('model', 80)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('printer_drivers', function (Blueprint $table) {
            $table->string('model', 80)->nullable(false)->default('')->change();
        });
    }
};
