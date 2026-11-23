<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-made label templates. company_id = the owner (null = made by the super admin, for everyone).
 * A client template is private to its company until the super admin publishes it (shared_at), which
 * is only allowed while the client agrees (share_consent). The owner stays visible to the super admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('label_templates', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->boolean('share_consent')->default(false)->after('is_default');
            $table->timestamp('shared_at')->nullable()->after('share_consent');
        });
    }

    public function down(): void
    {
        Schema::table('label_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['share_consent', 'shared_at']);
        });
    }
};
