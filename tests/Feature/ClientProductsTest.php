<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Companies\Company;
use App\Domain\Printing\Models\LabelTemplate;
use App\Http\Livewire\Catalog\ProductIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Client products (docs/spec/client-products.md): per company, own card, internal EAN-13, one label without a code. */
class ClientProductsTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $tin): User
    {
        $company = Company::create(['tin' => $tin, 'name' => 'Co ' . $tin, 'is_active' => true]);

        return User::create(['name' => $tin, 'email' => "$tin@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $company->id, 'company_role' => 'owner']);
    }

    public function test_internal_ean13_format_and_check_digit(): void
    {
        $user = $this->client('307797401');
        $next = Product::nextInternalGtin(7);
        $this->assertSame('02000070000014', $next['gtin']); // "20" + 0007 + 000001 + check digit 4, as GTIN-14
        $this->assertSame(1, $next['gtin_seq']);
        $this->assertSame(1, Product::checkDigit('200007000015')); // the example from the spec: …000015 → 2000070000151

        $this->actingAs($user);
        Livewire::test(ProductIndex::class)->call('openCreate')->call('generateGtin')
            ->assertSet('card.gtin', '20' . str_pad((string) $user->company_id, 4, '0', STR_PAD_LEFT) . '000001' . Product::checkDigit('20' . str_pad((string) $user->company_id, 4, '0', STR_PAD_LEFT) . '000001'))
            ->set('card.name', 'Ichki tovar')->set('card.description', 'Tavsif')->set('card.weight', '1 kg')
            ->call('save')->assertHasNoErrors();
        $p = Product::firstOrFail();
        $this->assertSame([$user->company_id, 'Ichki tovar', 1, true], [$p->company_id, $p->name, $p->gtin_seq, $p->hasInternalGtin()]);

        // the next one continues the company's sequence
        $this->assertSame(2, Product::nextInternalGtin($user->company_id)['gtin_seq']);
    }

    public function test_gtin_is_unique_per_company_only_and_validated(): void
    {
        $ali = $this->client('307797402');
        $bek = $this->client('307797403');
        foreach ([$ali, $bek] as $user) {
            $this->actingAs($user);
            Livewire::test(ProductIndex::class)->call('openCreate')->set('card.gtin', '4780000000052')->set('card.name', 'Choy')->call('save')->assertHasNoErrors();
        }
        $this->assertSame(2, Product::withoutGlobalScope('owner')->where('gtin', '04780000000052')->count()); // same GTIN, two companies

        $this->actingAs($ali);
        Livewire::test(ProductIndex::class)->call('openCreate')->set('card.gtin', '4780000000052')->set('card.name', 'Again')->call('save')->assertHasErrors('card.gtin');
        Livewire::test(ProductIndex::class)->call('openCreate')->set('card.gtin', '4780000000058')->set('card.name', 'Bad')->call('save')->assertHasErrors('card.gtin'); // wrong check digit
    }

    public function test_product_label_has_the_markor_logo_instead_of_the_code_and_no_one_else_can_print_it(): void
    {
        $ali = $this->client('307797404');
        $product = Product::withoutGlobalScope('owner')->create(['company_id' => $ali->company_id, 'gtin' => '04780000000059', 'name' => 'Choynak', 'description' => 'Oq rang', 'is_active' => true]);
        $template = LabelTemplate::create(['name' => 'T', 'printer_type' => 'pdf_browser', 'width_mm' => 40, 'height_mm' => 30, 'dpi' => 203, 'template' => '',
            'layout' => [['type' => 'datamatrix', 'x' => 2, 'y' => 2, 'w' => 16, 'h' => 16], ['type' => 'text', 'source' => 'description', 'x' => 20, 'y' => 2, 'w' => 18, 'h' => 6]]]);

        [, $html] = app(\App\Domain\Printing\Services\LabelPdfService::class)->productLabelHtml($template, $product);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html); // Markor logo in the code area
        $this->assertStringContainsString('Oq rang', $html);

        $this->actingAs($ali);
        $this->get(route('products.label', [$product, 'template' => $template->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('products.label.print', [$product, 'template' => $template->id]))->assertOk()->assertSee('Oq rang');

        $this->actingAs($this->client('307797405'));
        $this->get(route('products.label', [$product, 'template' => $template->id]))->assertNotFound();
    }

    public function test_super_admin_only_views_products_and_gtin_analytics(): void
    {
        $ali = $this->client('307797406');
        $product = Product::withoutGlobalScope('owner')->create(['company_id' => $ali->company_id, 'gtin' => '04780000000059', 'name' => 'Choynak', 'is_active' => true]);
        $admin = User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($admin);

        $this->assertFalse($admin->can('create', Product::class));
        $this->assertFalse($admin->can('update', $product));
        $this->assertFalse($admin->can('printLabel', $product));
        Livewire::test(ProductIndex::class)->assertSee('Choynak')->assertSee('Co 307797406')
            ->set('tab', 'gtin')->assertSee('04780000000059')
            ->call('openEdit', $product->id)->assertForbidden();
    }
}
