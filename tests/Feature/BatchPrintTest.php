<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Printing a batch from the import list follows the same rules as the PDF. */
class BatchPrintTest extends TestCase
{
    use RefreshDatabase;

    private function owner(string $tin, string $email): User
    {
        $company = Company::create(['tin' => $tin, 'name' => "C $tin", 'is_active' => true]);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'O', 'email' => $email, 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function batch(User $user): ImportBatch
    {
        Http::fake(['*' => Http::response(['success' => true, 'data' => []])]);
        $lines = [];
        for ($i = 0; $i < 3; $i++) {
            $lines[] = '0104780000000172' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('k', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'print.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    public function test_owner_prints_labels_and_codes_become_printed(): void
    {
        $batch = $this->batch($this->owner('303000011', 'p@t.uz'));
        $template = LabelTemplate::default();

        $html = $this->get(route('import-batches.print', [$batch, 'template' => $template->id, 'part' => 1]))->assertOk()
            ->assertSee('window.print()', false)->getContent();
        $this->assertSame(3, substr_count($html, 'class="label"'));
        $this->assertSame(3, MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->where('status', 'printed')->count());

        $list = $this->get(route('import-batches.index'))->assertOk()->getContent();
        // Print button on the batch row (the URL sits JSON-escaped inside the Alpine call)
        $this->assertStringContainsString('printUrl', $list);
        $this->assertStringContainsString(route('import-batches.print', $batch), str_replace('\\', '', $list));
    }

    public function test_locked_or_foreign_batch_cannot_be_printed(): void
    {
        $batch = $this->batch($this->owner('303000012', 'a@t.uz'));

        $batch->forceFill(['is_locked' => true])->save();
        $this->get(route('import-batches.print', $batch))->assertStatus(402);
        $batch->forceFill(['is_locked' => false])->save();

        $this->actingAs($this->owner('303000013', 'b@t.uz'));
        $this->get(route('import-batches.print', $batch))->assertNotFound();   // another company's batch is invisible (same as the PDF)
    }
}
