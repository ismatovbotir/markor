<?php

namespace Tests\Feature;

use App\Domain\Printing\Renderers\LayoutRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Standard signs on labels: the Asl Belgisi mark (public/images/marks/aslbelgi.png, black and white). */
class LabelMarkTest extends TestCase
{
    use RefreshDatabase;

    private function design(array $mark): array
    {
        return ['width_mm' => 40, 'height_mm' => 30, 'copies' => 1, 'watermark' => false,
            'elements' => [['type' => 'mark', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 8] + $mark]];
    }

    public function test_asl_belgisi_mark_prints_in_the_studio_and_unknown_marks_are_rejected(): void
    {
        $this->assertFileExists(public_path('images/marks/aslbelgi.png'));
        $mark = LayoutRenderer::mark('aslbelgi');
        $this->assertStringStartsWith('data:image/png;base64,', $mark['uri']);

        $html = $this->postJson(route('studio.print'), $this->design(['mark' => 'aslbelgi']))->assertOk()->getContent();
        $this->assertStringContainsString($mark['uri'], $html);

        $this->postJson(route('studio.print'), $this->design(['mark' => 'other']))->assertStatus(422);
        $this->postJson(route('studio.print'), $this->design([]))->assertStatus(422);
        $this->assertNull(LayoutRenderer::mark('../../.env'));
    }

    public function test_template_designer_offers_the_mark_and_the_renderer_draws_it(): void
    {
        $label = ['width_mm' => 30, 'height_mm' => 20, 'elements' => [['type' => 'mark', 'mark' => 'aslbelgi', 'x' => 1, 'y' => 1, 'w' => 8, 'h' => 8]]];
        $html = app(LayoutRenderer::class)->render($label, [], 0, now());
        $this->assertStringContainsString(LayoutRenderer::mark('aslbelgi')['uri'], $html);

        $this->assertStringContainsString("add('mark', 'aslbelgi')", file_get_contents(resource_path('views/livewire/printing/partials/template-editor.blade.php')));
        $this->assertStringContainsString("add('mark', 'aslbelgi')", file_get_contents(resource_path('views/studio/index.blade.php')));
    }
}
