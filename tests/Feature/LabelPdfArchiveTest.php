<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Printing\Models\LabelTemplate;
use App\Jobs\SendBatchPdfToTelegram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabelPdfArchiveTest extends TestCase
{
    use RefreshDatabase;

    private array $telegram = [];
    private ?string $archived = null;

    private function client(): User
    {
        $company = Company::create(['tin' => '303000001', 'name' => 'Arxiv MChJ']);

        return User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function batch(User $user): ImportBatch
    {
        $lines = [];
        for ($i = 0; $i < 3; $i++) {
            $lines[] = '0104780000000172' . '21' . substr(bin2hex(random_bytes(10)), 0, 20) . "\x1D91ABCD\x1D92" . str_repeat('k', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'arxiv.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    /** Fake Telegram: archives documents and serves them back via getFile. */
    private function fakeTelegram(): void
    {
        config(['telegram.token' => 'TEST', 'telegram.archive_chat' => '-1009999']);
        $this->classifier = Http::response(['success' => true, 'data' => []]);
        Http::fake(function ($request) {
            $url = $request->url();
            if (!str_contains($url, 'telegram.org')) {
                return str_contains($url, 'tasnif') ? Http::response(['success' => true, 'data' => []]) : null;
            }
            $method = basename(parse_url($url, PHP_URL_PATH));
            $this->telegram[] = $method;
            if ($method === 'sendDocument' && $request->isMultipart()) {
                foreach ($request->data() as $part) {
                    if (($part['name'] ?? '') === 'document') {
                        $this->archived = $part['contents'];
                    }
                }
                return Http::response(['ok' => true, 'result' => ['document' => ['file_id' => 'FILE-1', 'file_unique_id' => 'U1']]]);
            }
            if ($method === 'getFile') {
                return Http::response(['ok' => true, 'result' => ['file_path' => 'documents/a.pdf']]);
            }
            if (str_contains($url, '/file/botTEST/')) {
                return Http::response($this->archived);
            }
            return Http::response(['ok' => true, 'result' => []]);
        });
    }

    public function test_first_download_renders_and_archives_then_serves_from_telegram(): void
    {
        $this->fakeTelegram();
        $user = $this->client();
        $batch = $this->batch($user);

        $first = $this->get(route('import-batches.labels', $batch))->assertOk();
        $pdf = LabelPdf::firstOrFail();
        $this->assertSame('FILE-1', $pdf->telegram_file_id, 'archived to the admin chat (queue is sync in tests)');
        $this->assertNull($pdf->local_path, 'local copy removed after archiving');
        $this->assertSame('-1009999', $pdf->telegram_chat_id);

        $this->telegram = [];
        $second = $this->get(route('import-batches.labels', $batch))->assertOk();
        $this->assertSame($first->getContent(), $second->getContent());
        $this->assertContains('getFile', $this->telegram, 'second download comes from the Telegram archive');
        $this->assertNotContains('sendDocument', $this->telegram, 'no new render/upload');
        $this->assertSame(2, $pdf->fresh()->downloads);
    }

    public function test_changing_the_design_makes_the_pdf_stale(): void
    {
        $this->fakeTelegram();
        $batch = $this->batch($this->client());
        $this->get(route('import-batches.labels', $batch))->assertOk();
        $before = LabelPdf::first()->updated_at;

        $this->travel(1)->minutes();
        LabelTemplate::default()->touch();
        $this->telegram = [];
        $this->get(route('import-batches.labels', $batch))->assertOk();

        $this->assertContains('sendDocument', $this->telegram, 're-rendered and re-archived');
        $this->assertTrue(LabelPdf::first()->updated_at->gt($before));
    }

    public function test_bot_resends_archived_pdf_by_file_id(): void
    {
        $this->fakeTelegram();
        $user = $this->client();
        $user->update(['telegram_chat_id' => '777']);
        $batch = $this->batch($user);
        $this->get(route('import-batches.labels', $batch))->assertOk();

        $this->telegram = [];
        (new SendBatchPdfToTelegram($batch->id, LabelTemplate::default()->id, '777'))->handle(
            app(\App\Domain\Printing\Services\LabelPdfStore::class), app(\App\Domain\Telegram\TelegramApi::class), app(\App\Domain\Telegram\BotHandler::class));

        $this->assertSame(['sendDocument'], array_values(array_unique($this->telegram)));
        $this->assertNotContains('getFile', $this->telegram);
    }

    public function test_without_telegram_the_local_copy_is_kept_then_pruned(): void
    {
        config(['telegram.token' => null]);
        $batch = $this->batch($this->client());
        $this->get(route('import-batches.labels', $batch))->assertOk();
        $pdf = LabelPdf::firstOrFail();
        Storage::disk('local')->assertExists($pdf->local_path);

        $this->travel(8)->days();
        $this->artisan('pdfs:prune --days=7')->assertSuccessful();
        Storage::disk('local')->assertMissing($pdf->local_path);
        $this->assertNull($pdf->fresh()->local_path);
        $this->assertSame(1, LabelPdf::count(), 'the print log row stays');
    }
}
