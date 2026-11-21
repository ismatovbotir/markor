<?php

use App\Domain\Printing\Models\LabelTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Label templates (label sizes). `layout` (JSON) comes from the visual editor; `template` holds the
 * HTML compiled from it for dompdf. columns / gap_mm: several labels across a roll.
 *
 * REFERENCE DATA: exactly one built-in default template (60×40, is_default = true, locked against
 * edit/delete) is inserted here; LabelTemplate::default() relies on it right after `migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('printer_type', 20)->default('pdf_browser'); // zpl_zebra / ezpl_godex / sbpl_bixolon / pdf_browser
            $table->unsignedSmallInteger('width_mm')->default(60);
            $table->unsignedSmallInteger('height_mm')->default(40);
            $table->unsignedTinyInteger('columns')->default(1);
            $table->decimal('gap_mm', 4, 1)->default(0);
            $table->unsignedSmallInteger('labels_per_pdf')->default(2000); // a batch PDF is split into parts of this size (≈ one roll)
            $table->unsignedSmallInteger('dpi')->default(203);
            $table->text('template');
            $table->json('layout')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('label_templates')->insert([
            'name'         => 'Default 60×40',
            'printer_type' => 'pdf_browser',
            'width_mm'     => 60,
            'height_mm'    => 40,
            'columns'      => 1,
            'gap_mm'       => 0,
            'labels_per_pdf' => 2000,
            'dpi'          => 203,
            'layout'       => json_encode(LabelTemplate::DEFAULT_LAYOUT),
            'template'     => LabelTemplate::compileLayout(LabelTemplate::DEFAULT_LAYOUT, 60, 40),
            'is_default'   => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
