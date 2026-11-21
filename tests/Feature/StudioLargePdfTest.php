<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public designer must return a valid PDF also above one render chunk (50 labels → merged). */
class StudioLargePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_hundred_labels_come_back_as_one_valid_pdf(): void
    {
        $rows = [];
        for ($i = 1; $i <= 100; $i++) {
            $rows[] = ['Nomi' => "Mahsulot $i", 'EAN' => '478000000000' . ($i % 10)];
        }
        $response = $this->post(route('studio.generate'), [
            'width_mm' => 60, 'height_mm' => 60, 'columns' => 1, 'gap_mm' => 0, 'copies' => 1, 'rows' => $rows,
            'elements' => [
                ['type' => 'text', 'x' => 2, 'y' => 2, 'w' => 50, 'h' => 8, 'text' => '', 'source' => 'Nomi', 'font_size' => 9],
                ['type' => 'barcode', 'x' => 2, 'y' => 12, 'w' => 50, 'h' => 25, 'text' => '4780000000007', 'symbology' => 'ean13', 'source' => 'EAN'],
                ['type' => 'text', 'x' => 2, 'y' => 40, 'w' => 50, 'h' => 6, 'text' => '', 'source' => '@date', 'fmt' => 'd.m.Y'],
                ['type' => 'text', 'x' => 2, 'y' => 48, 'w' => 50, 'h' => 6, 'text' => '', 'source' => '@serial', 'prefix' => 'N', 'start' => 1, 'step' => 1, 'pad' => 4],
            ],
        ]);

        $body = $response->getContent();
        $this->assertSame(200, $response->getStatusCode(), substr(strip_tags($body), 0, 600));
        $this->assertStringStartsWith('%PDF', $body, 'starts with: ' . substr($body, 0, 400));
        $this->assertStringEndsWith("%%EOF", rtrim($body));
        $this->assertSame(100, preg_match_all('#/Type\s*/Page[^s]#', $body));
    }

    public function test_rejected_design_answers_json_not_a_redirect(): void
    {
        app()->setLocale('uz');
        $response = $this->withHeaders(['Accept' => 'application/pdf, application/json'])->post(route('studio.generate'), [
            'width_mm' => 60, 'height_mm' => 40, 'copies' => 1,
            'elements' => [['type' => 'text', 'x' => 1, 'y' => 1, 'w' => 50, 'h' => 30, 'text' => str_repeat('a', 2001)]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('elements.0.text');
        $this->assertStringNotContainsString('validation.', $response->json('errors')['elements.0.text'][0], 'message is translated');
    }

    public function test_long_text_up_to_2000_characters_is_fine(): void
    {
        $response = $this->post(route('studio.generate'), [
            'width_mm' => 60, 'height_mm' => 40, 'copies' => 1,
            'elements' => [['type' => 'text', 'x' => 1, 'y' => 1, 'w' => 58, 'h' => 38, 'text' => str_repeat('Matn ', 380), 'font_size' => 4]],
        ]);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_ean13_with_and_without_digits(): void
    {
        $b = new \App\Domain\Studio\BarcodeImage();
        $bars = getimagesizefromstring($b->png('ean13', '4780000000007', 6, false));
        $digits = getimagesizefromstring($b->png('ean13', '4780000000007', 6, true));
        $this->assertGreaterThan($bars[1], $digits[1], 'room for the digits under the bars');
        $this->assertGreaterThan($bars[0], $digits[0], 'room for the first digit on the left');
        $this->assertSame('4780000000007', \App\Domain\Studio\BarcodeImage::ean13Full('478000000000'), 'check digit added');

        $this->get(route('studio.barcode', ['type' => 'ean13', 'data' => '4780000000007', 'hrt' => 1]))->assertOk()->assertHeader('Content-Type', 'image/png');

        $response = $this->post(route('studio.generate'), [
            'width_mm' => 60, 'height_mm' => 40, 'copies' => 2,
            'elements' => [
                ['type' => 'barcode', 'x' => 2, 'y' => 2, 'w' => 50, 'h' => 16, 'text' => '4780000000007', 'symbology' => 'ean13', 'hrt' => true],
                ['type' => 'barcode', 'x' => 2, 'y' => 20, 'w' => 50, 'h' => 12, 'text' => '4780000000007', 'symbology' => 'ean13', 'hrt' => false],
            ],
        ]);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function codes(int $n): array
    {
        $lines = array_slice(file(base_path('.claude/sample/sh_0175_06979497790395_60_2026-11-20.csv'), FILE_IGNORE_NEW_LINES), 0, $n);

        return array_map('trim', $lines);
    }

    public function test_guest_file_of_marking_codes_is_recognised(): void
    {
        $codes = $this->codes(14);
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('kodlar.csv', implode("\n", $codes));

        $response = $this->post(route('studio.parse'), ['file' => $file], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(['Kod'], $response->json('headers'), 'one column, codes are not split at , ; "');
        $this->assertCount(14, $response->json('rows'), 'no line is taken as a header');
        $this->assertSame($codes[0], $response->json('rows.0.Kod'));
        $this->assertSame(10, $response->json('marking.limit'));
        $this->assertSame(14, $response->json('marking.count'));
    }

    public function test_guest_prints_up_to_ten_real_gs1_marks(): void
    {
        $codes = $this->codes(11);
        $element = ['type' => 'barcode', 'symbology' => 'asl', 'source' => 'Kod', 'text' => '', 'x' => 2, 'y' => 2, 'w' => 20, 'h' => 20];
        $rows = fn ($n) => array_map(fn ($c) => ['Kod' => $c], array_slice($codes, 0, $n));

        $ok = $this->post(route('studio.generate'), ['width_mm' => 30, 'height_mm' => 30, 'rows' => $rows(10), 'elements' => [$element]]);
        $this->assertStringStartsWith('%PDF', $ok->getContent());

        $more = $this->post(route('studio.generate'), ['width_mm' => 30, 'height_mm' => 30, 'rows' => $rows(11), 'elements' => [$element]])->getContent();
        $this->assertSame(10, preg_match_all('#/Type\s*/Page[^s]#', $more), 'only the first 10 codes'); 

        // the mark really is GS1: FNC1 first, GS before AI 91 / 92
        $payload = null;
        $parsed = app(\App\Domain\Marking\Parsers\MarkingCodeParser::class)->parse($codes[0]);
        $structure = \App\Domain\Catalog\Models\ProductGroup::find($parsed['product_group_id'])->structure;
        $payload = \App\Domain\Marking\Parsers\MarkingCodeParser::gs1Payload($parsed['code'], $structure);
        $this->assertStringStartsWith("\xE8", $payload);
        $this->assertSame(2, substr_count($payload, "\x1D"));
        $this->assertStringStartsWith('data:image/png;base64,', app(\App\Domain\Studio\MarkingTrial::class)->png($codes[0]));
    }

    public function test_upload_limits_size_and_real_type(): void
    {
        $big = \Illuminate\Http\UploadedFile::fake()->create('katta.csv', config('uploads.studio.max_kb') + 1, 'text/csv');
        $this->post(route('studio.parse'), ['file' => $big], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        $exe = \Illuminate\Http\UploadedFile::fake()->createWithContent('virus.csv', "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF" . random_bytes(2000));
        $this->post(route('studio.parse'), ['file' => $exe], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        $pdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('jadval.pdf', '%PDF-1.4 test');
        $this->post(route('studio.parse'), ['file' => $pdf], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_real_operator_file_with_gs_characters_is_accepted(): void
    {
        // work on a copy: the upload handler deletes the uploaded file after reading it
        $copy = tempnam(sys_get_temp_dir(), 'codes');
        copy(base_path('.claude/sample/sh_0175_06979497791620_14_2026-11-20.csv'), $copy);
        $real = new \Illuminate\Http\UploadedFile($copy, 'kodlar.csv', null, null, true);
        $this->assertSame('application/octet-stream', $real->getMimeType(), 'the system detector calls it binary');
        $this->post(route('studio.parse'), ['file' => $real], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('marking.count', 14);

        $xlsx = \Illuminate\Http\UploadedFile::fake()->createWithContent('jadval.xlsx', 'not a zip');
        $this->post(route('studio.parse'), ['file' => $xlsx], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('errors.file.0', __('upload.not_spreadsheet'));
    }
}
