<?php

namespace Tests\Feature;

use App\Domain\Marking\Services\ImportBatchService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function importAs(User $user, int $count)
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = '0104780000000042' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . substr(base64_encode(random_bytes(33)), 0, 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);

        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'c.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    private function client(?float $discount = null, ?string $until = null): User
    {
        $company = \App\Domain\Companies\Company::create(['tin' => (string) random_int(300000000, 399999999), 'name' => 'B', 'discount_percent' => $discount, 'discount_until' => $until]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => uniqid() . '@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_batch_records_price_and_default_discount(): void
    {
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 100]);
        $batch = $this->importAs($this->client(), 10);

        $this->assertSame(9.0, $batch->unit_price);
        $this->assertSame(100.0, $batch->discount_percent);
        $this->assertSame(0.0, $batch->amount);
    }

    public function test_client_discount_overrides_default(): void
    {
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 100]);
        $batch = $this->importAs($this->client(discount: 20), 10);

        $this->assertSame(20.0, $batch->discount_percent);
        $this->assertSame(72.0, $batch->amount); // 10 × 9 × 0.8
    }

    public function test_price_change_does_not_change_old_batches(): void
    {
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 0]);
        $client = $this->client();
        $old = $this->importAs($client, 5);

        config(['billing.price_per_code' => 12]);
        $new = $this->importAs($client, 5);

        $this->assertSame(45.0, $old->fresh()->amount);
        $this->assertSame(60.0, $new->amount);
    }

    public function test_company_discount_ends_after_its_date(): void
    {
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 0]);
        $client = $this->client(discount: 100, until: now()->addDays(3)->toDateString());

        $this->assertSame(0.0, $this->importAs($client, 4)->amount, 'inside the discount period');

        $this->travel(5)->days();
        $this->assertSame(36.0, $this->importAs($client, 4)->amount, 'after the period: default discount (0%)');
    }

    public function test_new_company_gets_one_month_free_period(): void
    {
        config(['billing.free_months' => 1, 'company.lookup_url' => 'https://lookup.test/{tin}', 'company.lookup_authorization' => 'Basic x']);
        \Illuminate\Support\Facades\Http::fake(['lookup.test/*' => \Illuminate\Support\Facades\Http::response(['name' => 'Yangi MChJ', 'tin' => '309876543']), '*' => \Illuminate\Support\Facades\Http::response([], 404)]);
        $user = User::create(['name' => 'N', 'email' => 'n@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($user);

        \Livewire\Livewire::test(\App\Http\Livewire\Account\CompanySetup::class)
            ->set('tin', '309876543')->call('lookup')->set('company.name', 'Yangi MChJ')->call('save');

        $company = $user->fresh()->company;
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $company->free_until->toDateString());
        $this->assertSame(100.0, \App\Domain\Billing\Billing::discountFor($company));
        $this->assertNull($company->discount_percent, 'the free period is not a discount');
    }
}
