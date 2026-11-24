<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Printing\LabelFonts;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Livewire\Printing\MyTemplates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Label text fonts: 10 embedded fonts besides the default DejaVu Sans, the same in editor, print page and PDF. */
class LabelFontsTest extends TestCase
{
    use RefreshDatabase;

    private function design(array $text): array
    {
        return [
            'width_mm' => 60, 'height_mm' => 30, 'columns' => 1, 'gap_mm' => 0, 'copies' => 1,
            'elements' => [['type' => 'text', 'x' => 2, 'y' => 2, 'w' => 56, 'h' => 10, 'font_size' => 9] + $text],
        ];
    }

    public function test_every_font_has_both_files(): void
    {
        $this->assertCount(10, LabelFonts::keys());
        foreach (LabelFonts::keys() as $key) {
            foreach ([400, 700] as $weight) {
                $this->assertFileExists(public_path("fonts/label/{$key}-{$weight}.ttf"));
            }
        }
    }

    public function test_pdf_embeds_the_chosen_font(): void
    {
        $response = $this->post(route('studio.generate'), $this->design(['text' => 'Oʻzbekiston Жж', 'font' => 'oswald', 'bold' => true]));

        $body = $response->getContent();
        $this->assertSame(200, $response->getStatusCode(), substr(strip_tags($body), 0, 600));
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertMatchesRegularExpression('#/BaseFont\s*/[A-Z]{6}\+Oswald#', $body);
        $this->assertDoesNotMatchRegularExpression('#/BaseFont\s*/[A-Z]{6}\+Tinos#', $body, 'unused fonts are not loaded');
    }

    public function test_unknown_font_is_rejected(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])
            ->post(route('studio.generate'), $this->design(['text' => 'A', 'font' => 'comic-sans']))
            ->assertStatus(422)->assertJsonValidationErrors('elements.0.font');
    }

    public function test_default_font_stays_as_before(): void
    {
        $css = LabelTemplate::textCss(['font_size' => 7]);
        $this->assertStringNotContainsString('font-family', $css);
        $this->assertSame($css, LabelTemplate::textCss(['font_size' => 7, 'font' => 'nope']), 'an unknown key falls back to DejaVu');
    }

    public function test_pdf_line_height_is_per_font(): void
    {
        $html = '<div style="' . LabelTemplate::textCss(['font' => 'oswald']) . '">a</div>'
            . '<div style="' . LabelTemplate::textCss(['font' => 'roboto']) . '">b</div>';
        $pdf = LabelFonts::pdfLineHeights($html);

        $this->assertStringContainsString('lbl-oswald,\'DejaVu Sans\',sans-serif;line-height:0.73;', $pdf);
        $this->assertStringContainsString('lbl-roboto,\'DejaVu Sans\',sans-serif;line-height:0.92;', $pdf);
        $this->assertStringNotContainsString(LabelTemplate::LINE_HEIGHT_CSS, $pdf);
    }

    public function test_okina_becomes_a_look_alike_only_where_the_font_lacks_it(): void
    {
        $this->assertSame("O\u{2018}zbekiston", LabelFonts::text("O\u{02BB}zbekiston", 'roboto'));
        $this->assertSame("O\u{02BB}zbekiston", LabelFonts::text("O\u{02BB}zbekiston", 'montserrat'));
        $this->assertSame("O\u{02BB}zbekiston", LabelFonts::text("O\u{02BB}zbekiston", null));
    }

    public function test_browser_print_page_loads_only_the_used_font(): void
    {
        $this->post(route('studio.print'), $this->design(['text' => 'Salom', 'font' => 'pt-serif']))
            ->assertOk()
            ->assertSee(asset('fonts/label/pt-serif-700.ttf'), false)
            ->assertDontSee('lbl-tinos', false);
    }

    public function test_client_template_keeps_the_font(): void
    {
        $company = Company::create(['tin' => '307797301', 'name' => 'Co', 'is_active' => true]);
        $user = User::create(['name' => 'U', 'email' => 'u@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'owner']);
        $this->actingAs($user);

        Livewire::test(MyTemplates::class)->call('openCopy')->call('save', [
            'name' => 'Shrift', 'width_mm' => 30, 'height_mm' => 20, 'columns' => 1, 'gap_mm' => 0, 'labels_per_pdf' => 2000,
            'elements' => [
                ['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 16, 'h' => 16],
                ['type' => 'text', 'x' => 19, 'y' => 2, 'w' => 10, 'h' => 5, 'text' => 'A', 'font' => 'montserrat'],
            ],
        ]);

        $this->assertSame('montserrat', LabelTemplate::where('name', 'Shrift')->firstOrFail()->layout[1]['font']);
    }
}
