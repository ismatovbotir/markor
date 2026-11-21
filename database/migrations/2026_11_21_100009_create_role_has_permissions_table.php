<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission: permissions of a role. Clears the package's permission cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $permission = config('permission.column_names.permission_pivot_key') ?? 'permission_id';
        $role = config('permission.column_names.role_pivot_key') ?? 'role_id';

        Schema::create($tables['role_has_permissions'], function (Blueprint $table) use ($tables, $permission, $role) {
            $table->unsignedBigInteger($permission);
            $table->unsignedBigInteger($role);
            $table->foreign($permission)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->foreign($role)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary([$permission, $role], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names.role_has_permissions', 'role_has_permissions'));
    }
};
