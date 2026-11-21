<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission: direct permissions of a model (polymorphic).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $pivot = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $morphKey, $pivot) {
            $table->unsignedBigInteger($pivot);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivot)->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->primary([$pivot, $morphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names.model_has_permissions', 'model_has_permissions'));
    }
};
