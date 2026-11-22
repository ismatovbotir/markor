<?php

namespace Tests\Feature;

use App\Domain\Billing\BalanceService;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Http\Livewire\Admin\BatchIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** CLAUDE.md §15: cancel a charge, delete a whole batch (printed: straight away), never a single code. */
class AdminBatchTest extends TestCase
{
    use RefreshDatabase;

    private array $lines = [];

    private function admin(): User
    {
        return User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    /** A paid (charged) batch of 3 codes; the company keeps 100 so'm after paying 27. */
    private function paidBatch(): ImportBatch
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);
        config(['billing.price_per_code' => 9, 'billing.discount_percent' => 0]);   // no promo discount: the batch is really charged
        $company = Company::create(['tin' => '305000001', 'name' => 'Pul MChJ', 'is_active' => true, 'free_until' => now()->subDay()]);
        $owner = User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'O', 'email' => 'o@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        app(BalanceService::class)->adjust($company, $this->admin(), 127, 'start');

        $this->lines = [];
        for ($i = 0; $i < 3; $i++) {
            $this->lines[] = '0104780000000172' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('k', 44);
        }
        $this->actingAs($owner);
        $batch = $this->upload();
        $this->assertNotNull($batch->charged_at, 'batch is charged');
        $this->assertGreaterThan(0, $batch->amount);

        return $batch;
    }

    private function upload(): ImportBatch
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $this->lines));
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'pul.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    public function test_unprinted_batch_needs_the_charge_cancelled_before_delete(): void
    {
        $batch = $this->paidBatch();
        $company = $batch->company;
        $before = (float) $company->fresh()->balance;
        $this->actingAs($admin = User::where('role', 'admin')->first());

        $this->get(route('admin.batches'))->assertOk()->assertSee('pul.csv')->assertSee('Pul MChJ');

        // delete is refused while the charge stands
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'delete')->set('reason', 'xato fayl')->call('confirm')->assertHasErrors('reason');
        $this->assertNotNull(ImportBatch::withoutGlobalScope('owner')->find($batch->id));

        // cancel the charge: money back, batch stays marked
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'cancel')->set('reason', '')->call('confirm')->assertHasErrors('reason')
            ->set('reason', 'xato fayl')->call('confirm')->assertHasNoErrors();
        $this->assertEqualsWithDelta($before + $batch->amount, (float) $company->fresh()->balance, 0.01);
        $this->assertNotNull($batch->fresh()->charge_cancelled_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'batch.charge_cancelled', 'entity_id' => $batch->id]);

        // a second cancel gives nothing back
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'cancel')->set('reason', 'yana')->call('confirm')->assertHasErrors('reason');
        $this->assertEqualsWithDelta($before + $batch->amount, (float) $company->fresh()->balance, 0.01);

        // now delete: batch and codes gone for good, balance unchanged, the same codes can be uploaded again
        $balance = (float) $company->fresh()->balance;
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'delete')->set('reason', 'xato fayl')->call('confirm')->assertHasNoErrors();
        $this->assertNull(ImportBatch::withoutGlobalScope('owner')->find($batch->id));
        $this->assertSame(0, MarkingCode::withoutGlobalScope('owner')->withTrashed()->where('import_batch_id', $batch->id)->count());
        $this->assertEqualsWithDelta($balance, (float) $company->fresh()->balance, 0.01);
        $this->assertDatabaseHas('audit_logs', ['action' => 'batch.deleted', 'entity_id' => $batch->id]);

        $this->actingAs(User::where('email', 'o@t.uz')->first());
        $again = $this->upload();
        $this->assertSame(3, $again->processed_rows, 'codes of a deleted batch upload again');
    }

    public function test_printed_batch_is_deleted_straight_away_and_the_charge_is_kept(): void
    {
        $batch = $this->paidBatch();
        $company = $batch->company;
        MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->limit(1)->update(['status' => 'printed', 'printed_at' => now()]);
        $balance = (float) $company->fresh()->balance;
        $this->actingAs(User::where('role', 'admin')->first());

        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'delete')->set('reason', 'mijoz so\'radi')->call('confirm')->assertHasNoErrors();

        $this->assertNull(ImportBatch::withoutGlobalScope('owner')->find($batch->id));
        $this->assertEqualsWithDelta($balance, (float) $company->fresh()->balance, 0.01, 'no refund for a printed batch');
        $entry = \App\Domain\Billing\BalanceEntry::where('company_id', $company->id)->where('type', 'charge')->latest('id')->first();
        $this->assertNotNull($entry, 'the charge stays in the ledger');
        $this->assertStringContainsString('pul.csv', (string) $entry->note);
    }

    public function test_cancel_also_returns_asl_belgisi_check_charges_and_waits_for_a_running_check(): void
    {
        $batch = $this->paidBatch();
        $company = $batch->company;
        $check = \App\Domain\Aslbelgi\AslbelgiCheck::withoutGlobalScope('owner')->create(['import_batch_id' => $batch->id, 'company_id' => $company->id,
            'codes' => 3, 'unit_price' => 10, 'amount' => 30, 'status' => 'running', 'checked' => 0, 'problems' => 0]);
        $this->actingAs(User::where('role', 'admin')->first());

        // a running check: neither cancel nor delete
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'cancel')->set('reason', 'xato')->call('confirm')->assertHasErrors('reason');

        $check->update(['status' => 'done', 'checked' => 3, 'finished_at' => now()]);
        $before = (float) $company->fresh()->balance;
        Livewire::test(BatchIndex::class)->call('open', $batch->id, 'cancel')->set('reason', 'xato')->call('confirm')->assertHasNoErrors();
        $this->assertEqualsWithDelta($before + $batch->amount + 30, (float) $company->fresh()->balance, 0.01, 'CSV + check charge back');
        $this->assertNotNull($check->fresh()->charge_cancelled_at);
        $this->assertSame(0.0, \App\Domain\Marking\Services\BatchAdminService::refundable($batch->fresh()), 'never twice');
    }

    public function test_super_admin_manages_batches_but_never_sees_code_values(): void
    {
        $batch = $this->paidBatch();
        $code = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->first();
        $this->actingAs($admin = User::where('role', 'admin')->first());

        $this->get(route('admin.batches'))->assertOk()->assertSee('pul.csv')->assertDontSee($code->code);   // the batch, not its codes
        $this->get(route('marking-codes.index'))->assertForbidden();
        $this->get(route('import-batches.labels', $batch))->assertForbidden();
        $this->get(route('import-batches.print', $batch))->assertForbidden();
        $this->get(route('import-batches.parts', $batch))->assertForbidden();
        $this->assertFalse($admin->can('view', $code));

        // the company's own user still gets them
        $this->actingAs(User::where('email', 'o@t.uz')->first());
        $this->get(route('import-batches.print', $batch))->assertOk();
    }

    public function test_storage_page_shows_numbers_per_company_and_no_code_values(): void
    {
        $batch = $this->paidBatch();
        $code = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->first();
        $this->actingAs(User::where('role', 'admin')->first());

        $html = $this->get(route('admin.storage'))->assertOk()->assertSee('Pul MChJ')->getContent();
        $this->assertStringNotContainsString($code->code, $html);
        $stats = app(\App\Domain\Reporting\CodeStorage::class)->perCompany()[$batch->company_id];
        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['batches']);
        $this->get(route('admin.companies.show', $batch->company_id))->assertOk()->assertSee(__('storage.company_block'))->assertDontSee($code->code);

        $this->actingAs(User::where('email', 'o@t.uz')->first());
        $this->get(route('admin.storage'))->assertForbidden();
    }

    public function test_single_codes_cannot_be_deleted_and_clients_cannot_open_the_page(): void
    {
        $batch = $this->paidBatch();
        $code = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->first();
        $admin = User::where('role', 'admin')->first();
        $this->assertFalse($admin->can('delete', $code));
        $this->assertFalse($admin->can('forceDelete', $code));

        $this->actingAs(User::where('email', 'o@t.uz')->first());
        $this->get(route('admin.batches'))->assertForbidden();
    }
}
