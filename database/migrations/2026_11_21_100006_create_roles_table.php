<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission: roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('permission.table_names.roles', 'roles'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names.roles', 'roles'));
    }
};
