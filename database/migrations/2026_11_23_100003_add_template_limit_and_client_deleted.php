<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * companies.template_limit — how many own label designs a client may keep (super admin sets it; 5 by default).
 * label_templates.client_deleted_at — the client removed its design: gone for the client, the super admin
 * still sees it ("deleted by the client") and may accept it as global (with the client's consent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('template_limit')->default(5)->after('marking_system');
        });
        Schema::table('label_templates', function (Blueprint $table) {
            $table->timestamp('client_deleted_at')->nullable()->after('shared_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('template_limit'));
        Schema::table('label_templates', fn (Blueprint $table) => $table->dropColumn('client_deleted_at'));
    }
};
