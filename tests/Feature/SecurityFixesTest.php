<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\CompanyProductName;
use App\Domain\Catalog\Models\Product;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\MarkingCode;
use App\Http\Livewire\Marking\ImportBatchIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $tin): User
    {
        $company = Company::create(['tin' => $tin, 'name' => "Firma $tin"]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'U', 'email' => "$tin@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function codeFor(User $user, Product $product, string $serial): void
    {
        MarkingCode::withoutGlobalScope('owner')->create(['code' => '01' . $product->gtin . '21' . $serial, 'gtin' => $product->gtin, 'serial' => $serial,
            'product_id' => $product->id, 'company_id' => $user->company_id, 'status' => 'imported']);
    }

    public function test_a_client_name_applies_only_to_its_own_labels(): void
    {
        $victim = $this->client('301000001');
        $attacker = $this->client('301000002');
        $product = Product::withoutGlobalScope('owner')->create(['gtin' => '04780000000999', 'attributes' => ['lookup' => 'not_found'], 'lookup_status' => 'not_found', 'is_active' => true]);
        $this->codeFor($victim, $product, 'VICTIM0000001');
        $this->codeFor($attacker, $product, 'FORGED0000001');

        $this->actingAs($attacker);
        Livewire::test(ImportBatchIndex::class)->call('openNaming', $product->id)->set('naming.name_uz', 'Yomon nom')->call('saveNaming')->assertHasNoErrors();

        $this->assertNull($product->fresh()->name_uz, 'shared product untouched');
        $victimCode = MarkingCode::withoutGlobalScope('owner')->where('serial', 'VICTIM0000001')->first();
        $this->assertSame($product->gtin, \App\Domain\Printing\Renderers\LayoutRenderer::markingRow($victimCode, null)['name'], "victim's labels don't get the attacker's name (GTIN until named)");

        $this->actingAs($victim);
        Livewire::test(ImportBatchIndex::class)->call('openNaming', $product->id)->set('naming.name_uz', 'Choynak')->call('saveNaming');
        $this->assertSame('Choynak', \App\Domain\Printing\Renderers\LayoutRenderer::markingRow($victimCode->fresh(), null)['name']);
        // and the victim can correct its own name later
        Livewire::test(ImportBatchIndex::class)->call('openNaming', $product->id)->assertSet('naming.name_uz', 'Choynak')->set('naming.name_uz', 'Elektr choynak')->call('saveNaming');
        $this->assertSame('Elektr choynak', CompanyProductName::where('company_id', $victim->company_id)->value('name_uz'));
    }

    public function test_deactivated_user_is_logged_out_on_the_next_request(): void
    {
        $user = $this->client('301000003');
        $this->actingAs($user)->get(route('import-batches.index'))->assertOk();
        $user->update(['is_active' => false]);
        $this->get(route('import-batches.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->client('301000004');
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), ['email' => $user->email, 'password' => 'wrong-pass'])->assertSessionHasErrors('email');
        }
        $this->post(route('login.post'), ['email' => $user->email, 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_qr_login_never_works_for_the_super_admin(): void
    {
        User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true, 'qr_token' => str_repeat('a', 32)]);
        $this->post(route('login.qr'), ['qr_token' => str_repeat('a', 32)])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_security_headers_on_pages(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_marking_preview_is_never_a_real_mark_and_the_trial_is_per_day(): void
    {
        $this->seed(\Database\Seeders\ProductGroupSeeder::class);
        $codes = array_map('trim', array_slice(file(base_path('.claude/sample/sh_0175_06979497790395_60_2026-11-20.csv'), FILE_IGNORE_NEW_LINES), 0, 22));

        $preview = $this->get(route('studio.barcode', ['type' => 'asl', 'data' => $codes[0]]))->assertOk()->getContent();
        $real = base64_decode(substr(app(\App\Domain\Studio\MarkingTrial::class)->png($codes[0]), strlen('data:image/png;base64,')));
        $this->assertNotSame(md5($real), md5($preview), 'the preview is a look-alike, not the GS1 mark');

        $el = ['type' => 'barcode', 'symbology' => 'asl', 'source' => 'Kod', 'text' => '', 'x' => 2, 'y' => 2, 'w' => 20, 'h' => 20];
        $rows = fn ($from) => array_map(fn ($c) => ['Kod' => $c], array_slice($codes, $from, 10));
        $this->post(route('studio.print'), ['width_mm' => 30, 'height_mm' => 30, 'rows' => $rows(0), 'elements' => [$el]])->assertOk();
        $this->post(route('studio.print'), ['width_mm' => 30, 'height_mm' => 30, 'rows' => $rows(0), 'elements' => [$el]])->assertOk(); // same codes again: fine
        $this->post(route('studio.print'), ['width_mm' => 30, 'height_mm' => 30, 'rows' => $rows(10), 'elements' => [$el]])->assertStatus(422); // 10 new codes the same day
    }

    public function test_heavy_public_requests_are_refused(): void
    {
        $elements = array_fill(0, 10, ['type' => 'barcode', 'symbology' => 'qr', 'text' => 'X', 'x' => 1, 'y' => 1, 'w' => 10, 'h' => 10]);
        $this->post(route('studio.print'), ['width_mm' => 30, 'height_mm' => 30, 'copies' => 300, 'elements' => $elements])
            ->assertStatus(422)->assertJsonPath('message', __('studio.too_heavy'));
    }

    public function test_blocked_company_is_stopped_in_open_livewire_pages(): void
    {
        $user = $this->client('301000005');
        $this->actingAs($user);
        $component = Livewire::test(ImportBatchIndex::class);
        $user->company->update(['is_active' => false]);
        $this->assertContains(\App\Http\Middleware\CompanyRequired::class, \Livewire\Livewire::getPersistentMiddleware());
    }

    public function test_company_lookup_result_cannot_be_set_from_the_browser(): void
    {
        $this->actingAs(User::create(['name' => 'N', 'email' => 'n@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]));
        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::test(\App\Http\Livewire\Account\CompanySetup::class)->set('lookupMessage', 'found');
    }

    public function test_client_edits_own_item_fields_gtin_fixed_and_tasnif_fill(): void
    {
        $client = $this->client('301000006');
        $other = $this->client('301000007');
        $product = Product::withoutGlobalScope('owner')->create(['gtin' => '06906974316793', 'attributes' => ['lookup' => 'not_found'], 'lookup_status' => 'not_found', 'is_active' => true]);
        $this->codeFor($client, $product, 'OWN0000000001');
        $this->classifier = \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => [['name' => 'Чойнак: Beltop, BT-EK-5510 оқ ранг', 'packages' => []]]]);

        $this->actingAs($client);
        Livewire::test(\App\Http\Livewire\Catalog\ProductIndex::class)
            ->call('openEdit', $product->id)
            ->call('fetchFromTasnif')->assertSet('name_uz', 'Beltop, BT-EK-5510 oq rang')  // no AI key in tests: transliterated
            ->set('gtin', '00000000000000')->set('sku', 'BT-5510')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('06906974316793', $product->fresh()->gtin, 'GTIN never changes');
        $this->assertNull($product->fresh()->name_uz, 'shared product untouched');
        $this->assertSame('BT-5510', $product->fresh()->forCompany($client->company_id)->sku);

        $this->actingAs($other);
        Livewire::test(\App\Http\Livewire\Catalog\ProductIndex::class)->call('openEdit', $product->id)->assertNotFound(); // invisible to other companies
    }

    public function test_unnamed_item_prints_its_gtin_as_the_name(): void
    {
        $client = $this->client('301000008');
        $product = Product::withoutGlobalScope('owner')->create(['gtin' => '04780000000777', 'attributes' => ['lookup' => 'not_found'], 'lookup_status' => 'not_found', 'is_active' => true]);
        $this->codeFor($client, $product, 'GTN0000000001');
        $code = MarkingCode::withoutGlobalScope('owner')->where('serial', 'GTN0000000001')->first();
        $this->assertSame('04780000000777', \App\Domain\Printing\Renderers\LayoutRenderer::markingRow($code, null)['name']);
    }
}
