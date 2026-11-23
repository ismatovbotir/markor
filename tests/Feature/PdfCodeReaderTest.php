<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Companies\Company;
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
}
