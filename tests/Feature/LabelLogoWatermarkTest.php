<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Domain\Printing\Services\LabelImage;
use App\Domain\Printing\Services\LabelPdfService;
use App\Domain\Printing\Services\LabelPdfStore;
use App\Http\Livewire\Account\CompanyLogo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Company logo on labels and the "markor.uz" mark. */
class LabelLogoWatermarkTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = '>markor.uz</div>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function user(string $tin, string $email, string $role = 'owner', ?Company $company = null): User
    {
        $company ??= Company::create(['tin' => $tin, 'name' => "C $tin", 'is_active' => true]);

        return User::create(['company_id' => $company->id, 'company_role' => $role, 'name' => 'U', 'email' => $email, 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function batch(User $user): ImportBatch
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);
        $lines = [];
        for ($i = 0; $i < 2; $i++) {
            $lines[] = '0104780000000172' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('k', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'logo.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    private function template(array $extra = [], bool $watermark = false, int $w = 40, int $h = 30): LabelTemplate
    {
        return LabelTemplate::create([
            'name' => 'Logo ' . uniqid(), 'printer_type' => 'pdf_browser', 'width_mm' => $w, 'height_mm' => $h, 'dpi' => 203,
            'template' => '', 'is_default' => false, 'watermark' => $watermark,
            'layout' => [['type' => 'datamatrix', 'x' => 1, 'y' => 1, 'w' => 14, 'h' => 14], ...$extra],
        ]);
    }

    private const LOGO_EL = ['type' => 'logo', 'x' => 18, 'y' => 1, 'w' => 20, 'h' => 10];

    /** A coloured gradient PNG with a transparent corner (so the print version has work to do). */
    private function colourPng(int $w = 900, int $h = 300): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        for ($x = 0; $x < $w; $x++) {
            imageline($img, $x, 0, $x, $h, imagecolorallocatealpha($img, (int) ($x / $w * 255), 80, 200, 0));
        }
        imagealphablending($img, false);
        imagefilledrectangle($img, 0, 0, 50, 50, imagecolorallocatealpha($img, 0, 0, 0, 127));
        ob_start();
        imagepng($img);

        return ob_get_clean();
    }

    private function uploadLogo(User $owner): Company
    {
        $this->actingAs($owner);
        $file = UploadedFile::fake()->createWithContent('logo.png', $this->colourPng());
        Livewire::test(CompanyLogo::class)->set('logo', $file)->assertHasNoErrors();

        return $owner->company->fresh();
    }

    public function test_owner_uploads_logo_and_a_black_and_white_print_version_is_made(): void
    {
        $company = $this->uploadLogo($this->user('305000001', 'o@t.uz'));

        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_print_path);
        $this->assertSame(1, $company->logo_version);

        $print = imagecreatefromstring(Storage::disk('public')->get($company->logo_print_path));
        $this->assertSame(600, imagesx($print));   // long side capped, nothing trimmed
        $this->assertSame(200, imagesy($print));
        $colours = [];
        for ($x = 0; $x < 600; $x += 7) {
            for ($y = 0; $y < 200; $y += 7) {
                $c = imagecolorsforindex($print, imagecolorat($print, $x, $y));
                $colours[$c['red'] . ',' . $c['green'] . ',' . $c['blue']] = true;
            }
        }
        $this->assertEqualsCanonicalizing(['0,0,0', '255,255,255'], array_keys($colours)); // pure black / white
        $corner = imagecolorsforindex($print, imagecolorat($print, 2, 2));
        $this->assertSame(255, $corner['red']); // transparency became white

        $this->get(route('company.setup'))->assertOk()->assertSee(__('company_logo.title'));

        // replace → old files deleted, version bumped; remove → nothing left
        $old = [$company->logo_path, $company->logo_print_path];
        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->createWithContent('l.jpg', $this->jpeg()))->assertHasNoErrors();
        foreach ($old as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        Livewire::test(CompanyLogo::class)->call('remove');
        $company->refresh();
        $this->assertNull($company->logo_path);
        $this->assertSame(3, $company->logo_version);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function jpeg(): string
    {
        $img = imagecreatetruecolor(100, 100);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        ob_start();
        imagejpeg($img);

        return ob_get_clean();
    }

    public function test_members_cannot_manage_the_logo(): void
    {
        $owner = $this->user('305000002', 'o2@t.uz');
        $member = $this->user('', 'm2@t.uz', 'member', $owner->company);
        $this->actingAs($member);

        Livewire::test(CompanyLogo::class)->assertForbidden();
        $this->get(route('company.setup'))->assertDontSee(__('company_logo.title'));
    }

    public function test_logo_upload_validation(): void
    {
        $this->actingAs($this->user('305000003', 'o3@t.uz'));

        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->createWithContent('logo.txt', 'hello'))->assertHasErrors('logo');
        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->create('big.png', 1100, 'image/png'))->assertHasErrors('logo');
        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->createWithContent('x.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>'))->assertHasErrors('logo');
        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->createWithContent('x.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="10" height="10"><image xlink:href="file:///etc/passwd"/></svg>'))->assertHasErrors('logo');
        $this->assertNull(auth()->user()->company->fresh()->logo_path);

        // a plain SVG is accepted (converted to a black-and-white PNG when Imagick reads SVG, otherwise kept)
        Livewire::test(CompanyLogo::class)->set('logo', UploadedFile::fake()->createWithContent('ok.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100" viewBox="0 0 200 100"><rect x="10" y="10" width="180" height="80" fill="#c00"/></svg>'))->assertHasNoErrors();
        $company = auth()->user()->company->fresh();
        $this->assertStringEndsWith('.svg', $company->logo_path);
        Storage::disk('public')->assertExists($company->logo_print_path);
        $this->assertNotNull(app(LabelImage::class)->companyLogo($company));
    }

    public function test_batch_labels_show_the_logo_of_that_company_only(): void
    {
        $ownerA = $this->user('305000004', 'a@t.uz');
        $companyA = $this->uploadLogo($ownerA);
        $logoA = app(LabelImage::class)->companyLogo($companyA)['uri'];
        $template = $this->template([self::LOGO_EL]);

        $batchA = $this->batch($ownerA);
        $html = $this->get(route('import-batches.print', [$batchA, 'template' => $template->id]))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, $logoA));

        [, $labels] = app(LabelPdfService::class)->labelsHtml($template, $batchA->printableCodes()->get(), 'x', app(LabelImage::class)->companyLogo($companyA));
        $this->assertStringContainsString($logoA, $labels[0]);

        // another company without a logo: nothing is drawn in the logo box, and never A's logo
        $ownerB = $this->user('305000005', 'b@t.uz');
        $batchB = $this->batch($ownerB);
        $html = $this->get(route('import-batches.print', [$batchB, 'template' => $template->id]))->assertOk()->getContent();
        $this->assertStringNotContainsString($logoA, $html);
        $this->assertStringNotContainsString(LabelImage::sample()['uri'], $html);
        $this->assertSame(2, substr_count($html, '<img')); // only the two DataMatrix codes
    }

    public function test_previews_and_editor_use_the_sample_logo(): void
    {
        $company = $this->uploadLogo($this->user('305000006', 'p@t.uz'));
        $template = $this->template([self::LOGO_EL]);

        $preview = app(LabelPdfService::class)->previewDocument($template);
        $this->assertStringContainsString(LabelImage::sample()['uri'], $preview);
        $this->assertStringNotContainsString(app(LabelImage::class)->companyLogo($company)['uri'], $preview);

        $pdf = app(LabelPdfService::class)->pdf($template, [app(LabelPdfService::class)->demoCode()], 'preview', LabelImage::sample());
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_logo_change_makes_stored_pdf_stale(): void
    {
        $owner = $this->user('305000007', 's@t.uz');
        $batch = $this->batch($owner);
        $withLogo = $this->template([self::LOGO_EL]);
        $plain = $this->template();
        $store = app(LabelPdfStore::class);

        [$pdf] = $store->get($batch, $withLogo, $owner);
        [$plainPdf] = $store->get($batch, $plain, $owner);
        $this->assertTrue($store->parts($batch, $withLogo)[0]['ready']);

        $this->uploadLogo($owner);
        $batch = $batch->fresh();
        $this->assertTrue($pdf->fresh('template')->isStaleFor($batch));
        $this->assertFalse($store->parts($batch, $withLogo)[0]['ready']);
        $this->assertFalse($plainPdf->fresh('template')->isStaleFor($batch)); // no logo element: unaffected

        [$again] = $store->get($batch, $withLogo, $owner);
        $this->assertSame(1, $again->logo_version);
        $this->assertFalse($again->isStaleFor($batch));
    }

    public function test_template_watermark_off_by_default_on_when_set_never_on_small_labels(): void
    {
        $service = app(LabelPdfService::class);
        $code = $service->demoCode();

        $off = $this->template();
        $this->assertFalse($off->fresh()->watermark);
        $this->assertStringNotContainsString(self::MARK, $service->labelsHtml($off, [$code])[1][0]);

        $on = $this->template(watermark: true);
        $this->assertStringContainsString(self::MARK, $service->labelsHtml($on, [$code])[1][0]);
        $this->assertStringContainsString(self::MARK, $service->previewDocument($on));

        $narrow = $this->template(watermark: true, w: 19, h: 30);
        $low = $this->template(watermark: true, w: 40, h: 9);
        $this->assertStringNotContainsString(self::MARK, $service->labelsHtml($narrow, [$code])[1][0]);
        $this->assertStringNotContainsString(self::MARK, $service->labelsHtml($low, [$code])[1][0]);

        // bottom-right, 0.5 mm inside the edge, 4 pt
        $mark = LayoutRenderer::watermark(40, 30);
        $this->assertStringContainsString('font-size:4pt', $mark);
        $this->assertStringContainsString('left:32.1mm;top:28mm;width:7.4mm;height:1.5mm', $mark);
    }

    private function studioDesign(array $extra = []): array
    {
        return [
            'width_mm' => 40, 'height_mm' => 30, 'copies' => 2,
            'elements' => [['type' => 'text', 'text' => 'Salom', 'x' => 1, 'y' => 1, 'w' => 20, 'h' => 5, 'font_size' => 7]],
        ] + $extra;
    }

    public function test_studio_watermark_on_by_default_and_off_when_unchecked(): void
    {
        $html = $this->postJson(route('studio.print'), $this->studioDesign())->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, self::MARK));

        $html = $this->postJson(route('studio.print'), $this->studioDesign(['watermark' => true]))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, self::MARK));

        $html = $this->postJson(route('studio.print'), $this->studioDesign(['watermark' => false]))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::MARK, $html);

        $html = $this->postJson(route('studio.print'), ['width_mm' => 19] + $this->studioDesign(['watermark' => true]))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::MARK, $html);

        $this->postJson(route('studio.print'), $this->studioDesign(['watermark' => 'maybe']))->assertStatus(422);
        $this->assertStringStartsWith('%PDF', $this->postJson(route('studio.generate'), $this->studioDesign())->assertOk()->getContent());
    }

    private function imageElement(string $src, array $extra = []): array
    {
        return ['type' => 'image', 'src' => $src, 'x' => 20, 'y' => 1, 'w' => 15, 'h' => 5] + $extra;
    }

    public function test_studio_own_image_is_validated_converted_and_never_stored(): void
    {
        Storage::fake('local');
        $png = 'data:image/png;base64,' . base64_encode($this->colourPng(300, 100));
        $design = $this->studioDesign();
        $design['elements'][] = $this->imageElement($png);

        $html = $this->postJson(route('studio.print'), $design)->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringNotContainsString($png, $html); // re-encoded (black-and-white), not the original bytes

        $design['elements'][1]['bw'] = false; // grayscale kept
        $this->postJson(route('studio.print'), $design)->assertOk();

        $bad = [
            'svg'      => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'notimage' => 'data:image/png;base64,' . base64_encode('this is not an image'),
            'base64'   => 'data:image/png;base64,@@@not-base64@@@',
            'big'      => 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF" . random_bytes(1100 * 1024)),
        ];
        foreach ($bad as $case => $src) {
            $d = $this->studioDesign();
            $d['elements'][] = $this->imageElement($src);
            $this->postJson(route('studio.print'), $d)->assertStatus(422)->assertJsonValidationErrors($case === 'big' ? 'elements.1.src' : 'elements.1.src');
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_studio_pdf_with_own_image(): void
    {
        $design = $this->studioDesign();
        $design['elements'][] = $this->imageElement('data:image/jpeg;base64,' . base64_encode($this->jpeg()));
        $this->assertStringStartsWith('%PDF', $this->postJson(route('studio.generate'), $design)->assertOk()->getContent());
    }

    public function test_admin_designer_saves_logo_element_and_watermark_flag(): void
    {
        $this->actingAs(User::create(['name' => 'A', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        $elements = [['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 16, 'h' => 16], self::LOGO_EL];
        $payload = ['name' => 'Logo tpl', 'width_mm' => 40, 'height_mm' => 30, 'columns' => 1, 'gap_mm' => 0, 'elements' => $elements];

        Livewire::test(\App\Http\Livewire\Printing\LabelTemplateIndex::class)->call('openCopy')
            ->assertSet('editor.watermark', false)->assertSet('editor.allowLogo', true)
            ->call('save', $payload)->assertHasNoErrors();
        $t = LabelTemplate::where('name', 'Logo tpl')->firstOrFail();
        $this->assertFalse($t->watermark);
        $this->assertTrue($t->usesLogo());

        Livewire::test(\App\Http\Livewire\Printing\LabelTemplateIndex::class)->call('openEdit', $t->id)
            ->call('save', ['watermark' => true] + $payload)->assertHasNoErrors();
        $this->assertTrue($t->fresh()->watermark);
    }
}
