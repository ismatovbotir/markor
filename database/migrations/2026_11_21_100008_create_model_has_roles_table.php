<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission: roles of a model (polymorphic).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $pivot = config('permission.column_names.role_pivot_key') ?? 'role_id';

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($tables, $morphKey, $pivot) {
            $table->unsignedBigInteger($pivot);
            $table->string('model_type');
            $table->unsignedBigInteger($morphKey);
            $table->index([$morphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivot)->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary([$pivot, $morphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names.model_has_roles', 'model_has_roles'));
    }
};
