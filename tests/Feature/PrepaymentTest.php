<?php

namespace Tests\Feature;

use App\Domain\Billing\BalanceService;
use App\Domain\Billing\Payment;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Http\Livewire\Account\BalancePage;
use App\Http\Livewire\Admin\PaymentIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PrepaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 0]);
    }

    private function client(float $balance = 0, ?float $discount = null): User
    {
        $company = Company::create(['tin' => (string) random_int(310000000, 319999999), 'name' => 'Pay MChJ', 'balance' => $balance, 'discount_percent' => $discount]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => uniqid() . '@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'adm@t.uz'], ['name' => 'A', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    private function upload(User $user, int $count): ImportBatch
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = '0104780000000196' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('m', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'b.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    public function test_enough_balance_charges_the_batch(): void
    {
        $client = $this->client(balance: 1000);
        $batch = $this->upload($client, 10); // 90 so'm

        $this->assertFalse($batch->is_locked);
        $this->assertNotNull($batch->charged_at);
        $this->assertSame(910.0, $client->company->fresh()->balance);
        $this->get(route('import-batches.labels', $batch))->assertOk();
    }

    public function test_short_balance_locks_until_payment_is_confirmed(): void
    {
        $client = $this->client(balance: 50);
        $batch = $this->upload($client, 10); // 90 > 50

        $this->assertTrue($batch->is_locked);
        $this->assertSame(50.0, $client->company->fresh()->balance, 'nothing charged while locked');
        $this->get(route('import-batches.labels', $batch))->assertStatus(402);
        $this->assertSame(0, app(\App\Domain\Marking\Services\MarkingCodeService::class)->paginate()->total(), 'codes of a locked batch are hidden');

        // client reports a transfer, admin confirms it
        Livewire::test(BalancePage::class)->assertSet('amount', '40')->set('amount', '1000')->call('reportPayment')->assertHasNoErrors();
        $payment = Payment::firstOrFail();
        $this->assertSame('pending', $payment->status);

        $this->actingAs($this->admin());
        Livewire::test(PaymentIndex::class)->call('open', $payment->id)->set('confirmAmount', '1000')->call('confirm');

        $this->assertFalse($batch->fresh()->is_locked);
        $this->assertSame(960.0, $client->company->fresh()->balance); // 50 + 1000 − 90
        $this->actingAs($client)->get(route('import-batches.labels', $batch))->assertOk();
    }

    public function test_payment_unlocks_oldest_batches_first_as_far_as_it_covers(): void
    {
        $client = $this->client(balance: 0);
        $a = $this->upload($client, 10); // 90
        $b = $this->upload($client, 20); // 180
        $this->assertTrue($a->is_locked && $b->is_locked);

        app(BalanceService::class)->recordPayment($client->company, $this->admin(), 100, 'cash', null);

        $this->assertFalse($a->fresh()->is_locked);
        $this->assertTrue($b->fresh()->is_locked, '10 left is not enough for the next one');
        $this->assertSame(10.0, $client->company->fresh()->balance);
        $this->assertSame(170.0, BalanceService::shortfall($client->company->fresh()));
    }

    public function test_welcome_discount_costs_nothing_and_never_locks(): void
    {
        $client = $this->client(balance: 0, discount: 100);
        $batch = $this->upload($client, 10);

        $this->assertSame(0.0, $batch->amount);
        $this->assertFalse($batch->is_locked);
    }

    public function test_rejected_payment_changes_nothing(): void
    {
        $client = $this->client(balance: 0);
        $this->actingAs($client);
        Livewire::test(BalancePage::class)->set('amount', '5000')->call('reportPayment');

        $this->actingAs($this->admin());
        Livewire::test(PaymentIndex::class)->call('open', Payment::first()->id)->set('note', 'Pul kelmadi')->call('reject');

        $this->assertSame('rejected', Payment::first()->status);
        $this->assertSame(0.0, $client->company->fresh()->balance);
    }

    public function test_client_cannot_open_admin_payments(): void
    {
        $this->actingAs($this->client())->get(route('admin.payments'))->assertForbidden();
    }

    public function test_free_period_ends_then_admin_prolongs_it(): void
    {
        $client = $this->client(balance: 0);
        $client->company->update(['free_until' => now()->addDays(2)->toDateString()]);
        $this->assertFalse($this->upload($client, 10)->is_locked, 'free period');

        $this->travel(3)->days();
        $this->assertTrue($this->upload($client, 10)->is_locked, 'payment due after the free period');

        $this->actingAs($this->admin());
        Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $client->company])->call('prolongFree', 1);
        $company = $client->company->fresh();
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $company->free_until->toDateString(), 'ended period: counted from today');

        Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $company])->call('prolongFree', 1);
        $this->assertSame(now()->addMonthsNoOverflow(2)->toDateString(), $company->fresh()->free_until->toDateString(), 'running period: counted from its end');
        $this->assertFalse($this->upload($client, 10)->is_locked, 'free again');

        $this->actingAs($this->admin());
        Livewire::test(\App\Http\Livewire\Admin\CompanyShow::class, ['company' => $company->fresh()])->call('endFree');
        $this->assertFalse(\App\Domain\Billing\Billing::isFree($company->fresh()));
    }

    public function test_client_cannot_prolong(): void
    {
        $client = $this->client();
        $this->actingAs($client)->get(route('admin.companies.show', $client->company))->assertForbidden();
    }
}
