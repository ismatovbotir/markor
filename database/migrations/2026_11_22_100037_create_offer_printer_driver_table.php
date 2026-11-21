<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which marketplace products a driver fits: one driver often serves several models of a brand
 * (e.g. one Godex package for G500U and G530U). New table — existing tables are not changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_printer_driver', function (Blueprint $table) {
            $table->foreignId('printer_driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->primary(['printer_driver_id', 'offer_id']);
            $table->index('offer_id'); // drivers of a product (marketplace page)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_printer_driver');
    }
};
