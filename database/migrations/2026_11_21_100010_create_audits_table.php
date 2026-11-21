<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * owen-it/laravel-auditing: change history of Auditable models (User, Product, ProductGroup,
 * MarkingCode, LabelTemplate, Printer, Aggregation). Note: bulk inserts (insertOrIgnore) of
 * marking codes bypass Eloquent and are not audited — by design, for speed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        $tableName = config('audit.drivers.database.table', 'audits');

        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $morphPrefix = config('audit.user.morph_prefix', 'user');

            $table->id();
            $table->string($morphPrefix . '_type')->nullable();
            $table->unsignedBigInteger($morphPrefix . '_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 1023)->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();
            $table->index([$morphPrefix . '_id', $morphPrefix . '_type']);
        });
    }

    public function down(): void
    {
        $connection = config('audit.drivers.database.connection', config('database.default'));
        Schema::connection($connection)->dropIfExists(config('audit.drivers.database.table', 'audits'));
    }
};
