<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission: permissions (User uses HasRoles). The app authorizes by users.role
 * and policies today; these tables are kept for the package. Teams are not supported here
 * (config permission.teams = false).
 */
return new class extends Migration
{
    public function up(): void
    {
        throw_if(config('permission.teams'), 'permission.teams is enabled: the permission migrations must add team_foreign_key.');

        Schema::create(config('permission.table_names.permissions', 'permissions'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names.permissions', 'permissions'));
    }
};
