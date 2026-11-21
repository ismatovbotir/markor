<?php

namespace Tests\Feature;

use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Domain\Printing\Services\LabelPdfService;
use App\Http\Livewire\Printing\LabelTemplateIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTemplateDesignerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    private array $elements = [
        ['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 20, 'h' => 20],
        ['type' => 'text', 'source' => 'name', 'x' => 24, 'y' => 2, 'w' => 34, 'h' => 6, 'font_size' => 8, 'bold' => true, 'align' => 'left'],
        ['type' => 'text', 'source' => 'gtin', 'caption' => true, 'x' => 24, 'y' => 9, 'w' => 34, 'h' => 4, 'font_size' => 6, 'align' => 'left'],
        ['type' => 'barcode', 'symbology' => 'code128', 'source' => 'gtin', 'x' => 24, 'y' => 14, 'w' => 34, 'h' => 8],
        ['type' => 'text', 'source' => '@serial', 'prefix' => 'P-', 'start' => 1, 'step' => 1, 'pad' => 3, 'x' => 2, 'y' => 30, 'w' => 30, 'h' => 4, 'font_size' => 6, 'rotation' => 0],
    ];

    public function test_admin_saves_a_studio_style_template(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(LabelTemplateIndex::class)
            ->call('openCopy')
            ->call('save', ['name' => 'Studio uslubi', 'width_mm' => 60, 'height_mm' => 40, 'columns' => 2, 'gap_mm' => 3, 'elements' => $this->elements])
            ->assertHasNoErrors();

        $t = LabelTemplate::where('name', 'Studio uslubi')->firstOrFail();
        $this->assertSame('barcode', $t->layout[3]['type']);
        $this->assertSame('gtin', $t->layout[3]['source']);
        $this->assertSame('@serial', $t->layout[4]['source']);
        $this->assertSame(2, (int) $t->columns);
    }

    public function test_template_renders_fields_barcodes_and_counter_per_code(): void
    {
        $code = app(LabelPdfService::class)->demoCode();
        $renderer = app(LayoutRenderer::class);
        $label = ['width_mm' => 60, 'height_mm' => 40, 'elements' => $this->elements];

        $html = $renderer->render($label, LayoutRenderer::markingRow($code, 'p1'), 4, now(), $code, LayoutRenderer::markingFields());

        $this->assertStringContainsString('GTIN: 04323456788176', $html);
        $this->assertStringContainsString('P-005', $html, 'counter for the 5th label');
        $this->assertSame(2, substr_count($html, '<img'), 'marking DataMatrix + Code 128');
    }

    public function test_template_pdf_for_marking_codes(): void
    {
        $t = LabelTemplate::create(['name' => 'T', 'width_mm' => 60, 'height_mm' => 40, 'columns' => 1, 'gap_mm' => 0, 'printer_type' => 'pdf_browser', 'dpi' => 203, 'is_default' => false,
            'layout' => $this->elements, 'template' => '']);
        $codes = collect(range(1, 3))->map(fn () => app(LabelPdfService::class)->demoCode());

        $pdf = app(LabelPdfService::class)->pdf($t, $codes, 'p1');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }
}
