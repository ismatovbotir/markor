<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfCodeReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_opens_the_reader_with_category_structures(): void
    {
        ProductGroup::create(['name_uz' => 'Tamaki', 'name_ru' => 'Табак', 'code_format' => 'tobacco_short', 'serial_length' => 7, 'crypto_length' => 13, 'is_active' => true,
            'structure' => [['ai' => '01', 'length' => 14], ['ai' => '21', 'length' => 7], ['ai' => '', 'length' => 13]]]);
        $company = Company::create(['tin' => '307797294', 'name' => 'Test', 'is_active' => true]);
        $this->actingAs(User::create(['name' => 'O', 'email' => 'o@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'member']));

        $this->get(route('import-batches.pdf-reader'))->assertOk()->assertSee('pdfCodeReader', false)->assertSee('Tamaki');
        $this->get(route('import-batches.index'))->assertSee(route('import-batches.pdf-reader'));
    }

    public function test_super_admin_is_sent_to_the_admin_area(): void
    {
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));

        $this->get(route('import-batches.pdf-reader'))->assertRedirect(route('admin.clients'));
    }

    private function client(string $email = 'o@t.uz', string $tin = '307797294'): User
    {
        $company = Company::create(['tin' => $tin, 'name' => 'Test ' . $tin, 'is_active' => true]);

        return User::create(['name' => 'O', 'email' => $email, 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'member']);
    }

    public function test_codes_read_from_a_pdf_are_imported_as_a_normal_batch(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        $codes = ["0104780000000017217SvU.!Ko\x1DzR5\"QSkFJ\"/=a", '0104780000000017217AbCdEfGzR5"QSkFJ"/=b', '0104780000000017217AbCdEfGzR5"QSkFJ"/=b'];

        \Livewire\Livewire::test(\App\Http\Livewire\Marking\PdfCodeReader::class)
            ->call('import', $codes, 'labels.pdf')
            ->assertReturned('')
            ->assertRedirect(route('import-batches.index'));

        $batch = ImportBatch::withoutGlobalScope('owner')->sole();
        $this->assertSame(['pending', 'pdf', 'labels.pdf', $user->company_id, $user->id],
            [$batch->status, $batch->origin, $batch->original_filename, $batch->company_id, $batch->uploaded_by]);
        $file = storage_path('app/imports/' . $batch->filename);
        // GS removed, the repeated code taken once: exactly what the CSV import reads
        $this->assertSame("0104780000000017217SvU.!KozR5\"QSkFJ\"/=a\n0104780000000017217AbCdEfGzR5\"QSkFJ\"/=b\n", file_get_contents($file));
        @unlink($file);

        // the same PDF is never imported twice by the company
        \Livewire\Livewire::test(\App\Http\Livewire\Marking\PdfCodeReader::class)
            ->call('import', ['0104780000000017217ZZZZZZZzR5"QSkFJ"/=c'], 'Labels.PDF')
            ->assertReturned($batch->duplicateMessage());
        $this->assertSame(1, ImportBatch::withoutGlobalScope('owner')->count());
    }

    public function test_pdf_import_refuses_empty_and_oversized_lists(): void
    {
        $this->actingAs($this->client());
        $reader = \Livewire\Livewire::test(\App\Http\Livewire\Marking\PdfCodeReader::class);

        $reader->call('import', ['', "\n"], 'a.pdf')->assertReturned(__('pdfreader.import_empty'));
        $too = array_map(fn ($i) => '01047800000000172' . str_pad((string) $i, 7, '0', STR_PAD_LEFT) . 'x', range(1, \App\Http\Livewire\Marking\PdfCodeReader::MAX_CODES + 1));
        $reader->call('import', $too, 'b.pdf')->assertReturned(__('pdfreader.import_too_many', ['max' => '10 000']));
        $this->assertSame(0, ImportBatch::withoutGlobalScope('owner')->count());
    }

    public function test_result_screen_has_the_preview_and_the_import_button(): void
    {
        $this->actingAs($this->client());

        $this->get(route('import-batches.pdf-reader'))
            ->assertSee('codes.slice(0, 10)', false)
            ->assertSee('importCodes()', false)
            ->assertSee('gtins.length === 1', false);
    }

    public function test_csv_upload_dialog_checks_the_codes_in_the_browser_first(): void
    {
        ProductGroup::create(['name_uz' => 'Tamaki', 'name_ru' => 'Табак', 'code_format' => 'tobacco_short', 'serial_length' => 7, 'crypto_length' => 13, 'is_active' => true,
            'structure' => [['ai' => '01', 'length' => 14], ['ai' => '21', 'length' => 7], ['ai' => '', 'length' => 13]]]);
        $this->actingAs($this->client());

        \Livewire\Livewire::test(\App\Http\Livewire\Marking\ImportBatchIndex::class)
            ->set('showForm', true)
            ->assertSeeHtml('window.checkCodesFile(text, groups)')
            ->assertSeeHtml('Tamaki')                    // the category structures reach the dialog
            ->assertSeeHtml('Faylda GTIN (mahsulot shtrix-kodlari) bor'); // "GTINs, not marking codes" message ready
    }
}
