<?php

namespace Tests\Feature;

use App\Domain\Billing\Billing;
use App\Domain\Companies\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class FreePeriodRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.discount_percent' => 0, 'billing.price_per_code' => 9, 'billing.free_codes_limit' => 100, 'billing.free_months' => 1,
            'company.lookup_url' => 'https://lookup.test/{tin}', 'company.lookup_authorization' => 'Basic x']);
    }

    private function signUp(string $email): User
    {
        $user = User::create(['name' => 'N', 'email' => $email, 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_free_month_only_for_a_company_the_lookup_found(): void
    {
        Http::fake(['lookup.test/309000001' => Http::response(['name' => 'Haqiqiy MChJ', 'tin' => '309000001']), 'lookup.test/*' => Http::response([], 404), '*' => Http::response([], 404)]);

        $real = $this->signUp('real@t.uz');
        Livewire::test(\App\Http\Livewire\Account\CompanySetup::class)->set('tin', '309000001')->call('lookup')->set('company.name', 'Haqiqiy MChJ')->call('save');
        $this->assertTrue(Billing::isFree($real->fresh()->company), 'found → free month');

        $fake = $this->signUp('fake@t.uz');
        Livewire::test(\App\Http\Livewire\Account\CompanySetup::class)->set('tin', '309999999')->call('lookup')->set('company.name', 'Soxta')->call('save');
        $this->assertFalse(Billing::isFree($fake->fresh()->company), 'unknown number → no free month until the admin approves');
    }

    public function test_free_period_is_capped_and_the_rest_is_charged(): void
    {
        $company = Company::create(['tin' => '309000002', 'name' => 'A', 'free_until' => now()->addMonth()->toDateString()]);

        $first = Billing::priceBatch($company, 60);
        $this->assertSame([0.0, 60], [$first['amount'], $first['free_codes']]);

        $second = Billing::priceBatch($company->fresh(), 60);  // 40 free left
        $this->assertSame(40, $second['free_codes']);
        $this->assertSame(180.0, $second['amount'], '20 paid codes × 9');

        $third = Billing::priceBatch($company->fresh(), 10);
        $this->assertSame([90.0, 0], [$third['amount'], $third['free_codes']]);
        $this->assertSame(0, Billing::freeCodesLeft($company->fresh()));
    }

    public function test_admin_granting_a_new_free_period_resets_the_allowance(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $company = Company::create(['tin' => '309000003', 'name' => 'B', 'free_until' => now()->subDay()->toDateString(), 'free_codes_used' => 100]);
        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $company])->call('prolongFree', 1);

        $this->assertTrue(Billing::isFree($company->fresh()));
        $this->assertSame(100, Billing::freeCodesLeft($company->fresh()));
    }
}
