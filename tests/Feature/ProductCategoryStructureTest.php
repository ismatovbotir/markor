<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Parsers\MarkingCodeParser;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_categories_parse_their_codes(): void
    {
        $parser = new MarkingCodeParser();

        $appliance = $parser->parse("010432345678817621AF9-&eDmMbl?6CkOyAEB\x1D91UZF0\x1D92TDRFasF7Lf27Qv4/muaq3qoqmZ5x3WAKhKoLmzlyNi4=");
        $this->assertSame('Maishiy texnika', ProductGroup::find($appliance['product_group_id'])->name_uz);
        $this->assertSame('AF9-&eDmMbl?6CkOyAEB', $appliance['serial']);

        $tobacco = $parser->parse('0106291071140717217SvU.!KozR5"QSkFJ"/=');
        $this->assertSame('Tamaki mahsulotlari', ProductGroup::find($tobacco['product_group_id'])->name_uz);
        $this->assertSame('7SvU.!K', $tobacco['serial']);

        $medicine = $parser->parse('0104780000000011211234567890123' . '91ABCD92' . str_repeat('x', 44));
        $this->assertSame('Dori vositalari', ProductGroup::find($medicine['product_group_id'])->name_uz);
    }

    public function test_admin_defined_category_is_used_for_parsing_and_gs_placement(): void
    {
        $group = ProductGroup::create([
            'name_uz' => 'Ichimliklar', 'code_format' => 'custom', 'is_active' => true, 'sort_order' => 0,
            'structure' => [
                ['ai' => '01', 'label' => 'GTIN', 'length' => 14, 'variable' => false],
                ['ai' => '21', 'label' => 'Serial', 'length' => 6, 'variable' => true],
                ['ai' => '93', 'label' => 'Crypto', 'length' => 4, 'variable' => false],
            ],
        ]);
        $this->assertSame(30, $group->code_length);

        $parsed = (new MarkingCodeParser())->parse('010478000000002821ABC12393Zx9Q');
        $this->assertSame($group->id, $parsed['product_group_id']);
        $this->assertSame('ABC123', $parsed['serial']);

        $code = new MarkingCode(['code' => $parsed['code'], 'gtin' => $parsed['gtin'], 'serial' => $parsed['serial'], 'crypto_tail' => $parsed['crypto_tail']]);
        $code->product_group_id = $group->id;
        $this->assertSame("\xE8" . "0104780000000028" . "21ABC123" . "\x1D" . "93Zx9Q", (new DataMatrixRenderer())->buildGs1Payload($code));
    }

    public function test_code_matching_no_category_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new MarkingCodeParser())->parse('0104780000000028211234');
    }

    public function test_client_cannot_manage_categories(): void
    {
        $client = \App\Models\User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($client)->get(route('product-groups.index'))->assertForbidden();
    }
}
