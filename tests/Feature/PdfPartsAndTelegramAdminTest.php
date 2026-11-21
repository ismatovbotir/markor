<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfStore;
use App\Domain\Telegram\TelegramLink;
use App\Http\Livewire\Admin\TelegramPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PdfPartsAndTelegramAdminTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $company = Company::create(['tin' => '304000001', 'name' => 'Parts MChJ']);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'phone' => '+998901112233', 'is_active' => true]);
    }

    private function batch(User $user, int $count)
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = '0104780000000189' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('p', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'big.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    public function test_batch_is_split_into_parts_of_whole_rows(): void
    {
        $template = LabelTemplate::default();
        $template->update(['labels_per_pdf' => 100, 'columns' => 3]);   // → 99 labels per part (33 rows)
        $batch = $this->batch($this->client(), 250);

        $parts = $this->getJson(route('import-batches.parts', ['batch' => $batch, 'template' => $template->id]))->assertOk()->json();
        $this->assertSame([[1, 1, 99], [2, 100, 198], [3, 199, 250]], array_map(fn ($p) => [$p['part'], $p['first'], $p['last']], $parts));

        $this->get(route('import-batches.labels', ['batch' => $batch, 'template' => $template->id, 'part' => 3]))->assertOk();
        $pdf = LabelPdf::where('part', 3)->firstOrFail();
        $this->assertSame(52, $pdf->labels);
        $this->assertSame(199, $pdf->first_label);
        $this->assertSame(3, $pdf->parts);
        $this->assertTrue(collect(app(LabelPdfStore::class)->parts($batch, $template))->firstWhere('part', 3)['ready']);
    }

    public function test_small_batch_is_one_part(): void
    {
        $batch = $this->batch($this->client(), 5);
        $parts = $this->getJson(route('import-batches.parts', $batch))->json();
        $this->assertCount(1, $parts);
        $this->assertSame(5, $parts[0]['labels']);
    }

    public function test_admin_telegram_lists_and_webhook(): void
    {
        config(['telegram.token' => 'TEST', 'app.url' => 'https://markor.uz']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'markor_bot', 'first_name' => 'Markor', 'url' => '']])]);
        $client = $this->client();
        TelegramLink::create(['user_id' => $client->id, 'token' => 'a', 'chat_id' => '1', 'status' => 'phone_mismatch', 'shared_phone' => '+998909999999', 'tg_username' => 'ali_tg', 'expires_at' => now()]);
        TelegramLink::create(['user_id' => $client->id, 'token' => 'b', 'chat_id' => '1', 'status' => 'linked', 'shared_phone' => '+998901112233', 'expires_at' => now()]);
        $admin = User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);

        $this->actingAs($admin);
        Livewire::test(TelegramPage::class)
            ->set('tab', 'unmatched')->assertSee('+998909999999')->assertSee('ali_tg')
            ->set('tab', 'matched')->assertSee('+998901112233')
            ->call('setWebhook')->assertSee('Webhook o\'rnatildi');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'setWebhook') && str_starts_with($r['url'], 'https://markor.uz/telegram/webhook/'));
        $this->actingAs($client)->get(route('admin.telegram'))->assertForbidden();
    }
}
