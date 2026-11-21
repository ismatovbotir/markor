<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Http\Livewire\Account\CompanySetup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CompanySetupTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::create(['name' => 'C', 'email' => uniqid() . '@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_new_client_must_register_company_first(): void
    {
        $this->actingAs($this->client());
        $this->get(route('import-batches.index'))->assertRedirect(route('company.setup'));
        $this->get(route('products.index'))->assertRedirect(route('company.setup'));
        $this->get(route('company.setup'))->assertOk();
        $this->get(route('profile'))->assertOk();
        $this->get(route('help', 'company'))->assertOk();
    }

    public function test_lookup_fills_the_form_from_the_tax_number(): void
    {
        config(['company.lookup_url' => 'https://app.hippo.uz/roaming-reference-client-api/company/get/{tin}', 'company.lookup_authorization' => 'Basic test']);
        Http::fake(['app.hippo.uz/*' => Http::response([
            'inn' => '313471870', 'short_name' => '"JAXON BIZNES TRADE" MCHJ', 'name' => '"JAXON BIZNES TRADE" MAS\'ULIYATI CHEKLANGAN JAMIYAT',
            'mfo' => '00433', 'account' => '20208000707568915001', 'address' => 'Qorayontoq ko‘chasi, 33-uy', 'director' => 'KURONBOYEV BAXTIYOR AZIMBOYEVICH',
        ])]);
        $user = $this->client();
        $this->actingAs($user);

        Livewire::test(CompanySetup::class)
            ->set('tin', '313 471 870')->call('lookup')
            ->assertSet('lookupMessage', 'found')
            ->assertSet('company.name', '"JAXON BIZNES TRADE" MCHJ')
            ->assertSet('company.director', 'KURONBOYEV BAXTIYOR AZIMBOYEVICH')
            ->call('save')->assertRedirect(route('import-batches.index'));

        $company = $user->fresh()->company;
        $this->assertSame('313471870', $company->tin);
        $this->assertSame('lookup', $company->source);
        $this->assertSame('KURONBOYEV BAXTIYOR AZIMBOYEVICH', $company->director);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Basic test') && str_ends_with($r->url(), '/313471870'));
    }

    public function test_not_found_lets_client_type_details(): void
    {
        config(['company.lookup_url' => 'https://app.hippo.uz/x/{tin}']);
        Http::fake(['app.hippo.uz/*' => Http::response(['error' => 'not found'], 404)]);
        $this->actingAs($this->client());

        Livewire::test(CompanySetup::class)
            ->set('tin', '305000001')->call('lookup')
            ->assertSet('lookupMessage', 'not_found')
            ->set('company.name', 'Texno Import MCHJ')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('manual', Company::first()->source);
    }

    public function test_tax_number_rules(): void
    {
        Company::create(['tin' => '309000000', 'name' => 'Taken']);
        $this->actingAs($this->client());

        Livewire::test(CompanySetup::class)->set('tin', '12345')->call('lookup')->assertHasErrors('tin');
        Livewire::test(CompanySetup::class)->set('tin', '309000000')->call('lookup')->assertHasErrors('tin');
        Livewire::test(CompanySetup::class)->set('tin', '30809672160031')->call('lookup')->assertHasNoErrors(); // 14-digit PINFL
    }
}
