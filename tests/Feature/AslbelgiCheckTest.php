<?php

namespace Tests\Feature;

use App\Domain\Aslbelgi\AslbelgiCheck;
use App\Domain\Billing\BalanceEntry;
use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfStore;
use App\Http\Livewire\Marking\ImportBatchIndex;
use App\Jobs\SendBatchPdfToTelegram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

/** "Asl Belgisi tekshiruvi" of a CSV batch: order, charge, problem codes blocked from printing, key reminders. */
class AslbelgiCheckTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'c09d906f-1111-4ae5-2222-61c0934bcd59';
    private const TIN = '307797292';
    private const API = 'xtrace.aslbelgisi.uz/public/api/cod/private/codes';

    /** @var list<string> log lines + context written during the test */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Sleep::fake(); // retry backoff and throttling without waiting
        Event::listen(MessageLogged::class, fn (MessageLogged $e) => $this->logs[] = $e->message . ' ' . json_encode($e->context));
    }

    private function company(bool $key = true, float $balance = 1000): Company
    {
        $company = Company::create(['tin' => self::TIN, 'name' => 'Check MChJ', 'is_active' => true, 'free_until' => now()->addMonth()->toDateString()]);
        $company->forceFill(['balance' => $balance] + ($key ? ['aslbelgi_api_key' => self::KEY, 'aslbelgi_api_key_expires_at' => now()->addDays(60)] : []))->save();

        return $company;
    }

    private function user(Company $company, string $role = 'owner', string $email = 'o@t.uz'): User
    {
        return User::create(['company_id' => $company->id, 'company_role' => $role, 'name' => $role, 'email' => $email, 'password' => 'secret123',
            'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
    }

    private function batch(User $user, int $count = 5): ImportBatch
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = '0104780000000172' . '21' . sprintf('S%019d', $i + 1) . "\x1D91ABCD\x1D92" . str_repeat('k', 44);
        }
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, implode("\n", $lines));
        $this->actingAs($user);
        $batch = app(ImportBatchService::class)->createFromUpload(new UploadedFile($path, 'check.csv', 'text/csv', null, true));
        $this->processBatches();

        return $batch->fresh();
    }

    /** identification codes of the batch in id order (what is sent to the API) */
    private function idents(ImportBatch $batch): array
    {
        return MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->orderBy('id')->get()
            ->map(fn ($c) => '01' . $c->gtin . '21' . $c->serial)->all();
    }

    /**
     * Fakes cod/private/codes. $answer(ident, position) → ['status' => .., 'owner' => ..] | 'forbidden' | null (not found).
     */
    private function fakeApi(\Closure $answer): void
    {
        Http::fake([self::API => function (Request $request) use ($answer) {
            $results = [];
            $forbidden = [];
            foreach ($request->data()['codes'] as $i => $code) {
                $a = $answer($code, $i);
                if ($a === 'forbidden') {
                    $forbidden[] = $code;
                } elseif (is_array($a)) {
                    $results[] = [
                        'codeData' => ['code' => $code . "\u{001d}91ABCD", 'status' => $a['status']], // the API may echo more than the identification code
                        'packageData' => ['packageType' => 'UNIT', 'emptyPackage' => false, 'actuallyPacked' => 0],
                        'turnoverData' => ['ownerInfo' => ['ownerTin' => $a['owner'] ?? self::TIN]],
                    ];
                }
            }

            return Http::response(['results' => $results, 'forbiddenCodes' => $forbidden]);
        }]);
    }

    private function order(ImportBatch $batch)
    {
        return Livewire::test(ImportBatchIndex::class)->call('openCheck', $batch->id)->call('orderCheck');
    }

    private function assertKeyNeverLeaked(string ...$contents): void
    {
        foreach ([...$contents, implode("\n", $this->logs)] as $content) {
            $this->assertStringNotContainsString(self::KEY, $content);
        }
    }

    public function test_order_charges_10_per_code_without_discount_and_blocks_problem_codes(): void
    {
        $company = $this->company(balance: 1000);
        $owner = $this->user($company);
        $batch = $this->batch($this->user($company, 'member', 'm@t.uz'), 5); // any user of the company may order
        $this->assertSame(0.0, $batch->amount); // the CSV itself is free (discount / free period)

        $this->fakeApi(fn ($code, $i) => [
            0 => ['status' => 'INTRODUCED'],
            1 => 'forbidden',
            2 => null,
            3 => ['status' => 'RECEIVED', 'owner' => '999999999'],
            4 => ['status' => 'RECEIVED'],
        ][$i]);

        $html = $this->order($batch)->assertDispatched('notify', type: 'success')->html();

        $check = AslbelgiCheck::sole();
        $this->assertSame(['done', 5, 4, 50.0, 10.0], [$check->status, $check->checked, $check->problems, $check->amount, $check->unit_price]);
        $this->assertSame(950.0, $company->fresh()->balance);
        $this->assertSame([-50.0], BalanceEntry::where('type', 'aslbelgi_check')->pluck('amount')->all());
        $this->assertSame(0, BalanceEntry::where('type', 'aslbelgi_refund')->count());

        $codes = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->orderBy('id')->get();
        $this->assertSame(['introduced', 'not_ours', 'unknown', 'not_ours', null], $codes->pluck('aslbelgi_problem')->all());
        $this->assertSame(['INTRODUCED', null, null, 'RECEIVED', 'RECEIVED'], $codes->pluck('aslbelgi_status')->all());
        $this->assertNotNull($codes->first()->aslbelgi_checked_at);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://' . self::API && $r->hasHeader('Authorization', 'Bearer ' . self::KEY)
            && $r->data()['codes'] === $this->idents($batch) && $r->data()['addChildCodes'] === false);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'xtrace') && !str_starts_with($r->url(), 'https://xtrace.aslbelgisi.uz/public/api/cod/private/codes'));

        // only the good code is printed / in the PDF parts
        $template = LabelTemplate::default();
        $print = $this->get(route('import-batches.print', [$batch, 'template' => $template->id]))->assertOk()->getContent();
        $this->assertSame(1, substr_count($print, 'class="label"'));
        $parts = $this->getJson(route('import-batches.parts', [$batch, 'template' => $template->id]))->json();
        $this->assertSame(1, $parts[0]['labels']);

        $list = $this->get(route('import-batches.index'))->assertOk()
            ->assertSee(__('aslbelgi_check.problems', ['count' => 4]))
            ->assertSee(__('aslbelgi_check.reason.not_ours'))->assertSee(__('aslbelgi_check.reason.unknown'))->assertSee(__('aslbelgi_check.reason.introduced'))
            ->getContent();
        $this->assertKeyNeverLeaked($html, $list, $print, json_encode($check->toArray()));

        $this->actingAs($owner);
        $this->get(route('balance'))->assertOk()->assertSee(__('balance.type.aslbelgi_check'));
    }

    public function test_insufficient_balance_refuses_and_shows_the_shortfall(): void
    {
        $company = $this->company(balance: 20);
        $batch = $this->batch($this->user($company), 5); // 5 × 10 = 50
        Http::fake([self::API => Http::response(['results' => [], 'forbiddenCodes' => []])]);

        Livewire::test(ImportBatchIndex::class)->call('openCheck', $batch->id)
            ->assertSee(__('aslbelgi_check.insufficient', ['need' => \App\Domain\Billing\Billing::money(30)]))
            ->assertSee(route('balance'))
            ->assertDontSee(__('aslbelgi_check.confirm', ['amount' => \App\Domain\Billing\Billing::money(50)]))
            ->call('orderCheck')->assertDispatched('notify', type: 'error');

        $this->assertSame(0, AslbelgiCheck::count());
        $this->assertSame(20.0, $company->fresh()->balance);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'xtrace'));
    }

    public function test_api_down_charges_nothing(): void
    {
        $company = $this->company(balance: 100);
        $batch = $this->batch($this->user($company), 3);
        Http::fake([self::API => Http::response(null, 503)]);

        $this->order($batch);

        $check = AslbelgiCheck::sole();
        $this->assertSame(['failed', 0, 0.0, 'unreachable'], [$check->status, $check->checked, $check->amount, $check->error]);
        $this->assertSame(100.0, $company->fresh()->balance);
        $this->assertSame([-30.0, 30.0], BalanceEntry::orderBy('id')->pluck('amount')->all()); // charged at the order, refunded
        Http::assertSentCount(3 + 0); // three tries, then gave up (the classifier stub sends nothing for xtrace)
        $this->assertNull(MarkingCode::withoutGlobalScope('owner')->whereNotNull('aslbelgi_checked_at')->first());
        $this->get(route('import-batches.index'))->assertSee(__('aslbelgi_check.error.unreachable'));
    }

    public function test_invalid_key_charges_nothing_and_never_logs_the_key(): void
    {
        $company = $this->company(balance: 100);
        $batch = $this->batch($this->user($company), 3);
        // the error body echoes the Authorization header (OPEN API p.10)
        Http::fake([self::API => Http::response([['code' => 'access-denied', 'errorId' => 'e1', 'service' => 'cod-reader',
            'context' => ['description' => "Provided token isn't active", 'Authorization' => 'Bearer ' . self::KEY]]], 401)]);

        $html = $this->order($batch)->html();

        $check = AslbelgiCheck::sole();
        $this->assertSame(['failed', 0.0, 'key_invalid'], [$check->status, $check->amount, $check->error]);
        $this->assertSame(100.0, $company->fresh()->balance);
        Http::assertSentCount(1); // 401 is never retried
        $this->assertNotEmpty($this->logs);
        $list = $this->get(route('import-batches.index'))->assertSee(__('aslbelgi_check.error.key_invalid'))->getContent();
        $this->assertKeyNeverLeaked($html, $list, json_encode(AslbelgiCheck::first()->toArray()), json_encode(\DB::table('jobs')->get()));
    }

    public function test_partial_check_charges_only_checked_codes(): void
    {
        config(['aslbelgi.check.chunk' => 2]);
        $company = $this->company(balance: 100);
        $batch = $this->batch($this->user($company), 5);
        $calls = 0;
        Http::fake([self::API => function (Request $r) use (&$calls) {
            return ++$calls === 1
                ? Http::response(['results' => array_map(fn ($c) => ['codeData' => ['code' => $c, 'status' => 'RECEIVED'], 'turnoverData' => ['ownerInfo' => ['ownerTin' => self::TIN]]], $r->data()['codes']), 'forbiddenCodes' => []])
                : Http::response(null, 500);
        }]);

        $this->order($batch);

        $check = AslbelgiCheck::sole();
        $this->assertSame(['partial', 2, 20.0, 'unreachable'], [$check->status, $check->checked, $check->amount, $check->error]);
        $this->assertSame(80.0, $company->fresh()->balance);
        $this->assertEquals(30, BalanceEntry::where('type', 'aslbelgi_refund')->sum('amount'));
        $this->assertSame(2, MarkingCode::withoutGlobalScope('owner')->whereNotNull('aslbelgi_checked_at')->count());
    }

    public function test_307_marks_codes_as_not_ours(): void
    {
        $company = $this->company();
        $batch = $this->batch($this->user($company), 3);
        Http::fake([self::API => Http::response(null, 307, ['Location' => 'https://xtrace.aslbelgisi.uz/public/api/cod/public/codes'])]);

        $this->order($batch);

        $this->assertSame('done', AslbelgiCheck::sole()->status);
        $this->assertSame(30.0, AslbelgiCheck::sole()->amount);
        $this->assertSame(3, MarkingCode::withoutGlobalScope('owner')->where('aslbelgi_problem', 'not_ours')->count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'cod/public/codes')); // the redirect is not followed
        $this->get(route('import-batches.index'))->assertSee(__('aslbelgi_check.nothing_printable'));
        $this->get(route('import-batches.print', $batch))->assertStatus(409);
        $this->get(route('import-batches.labels', $batch))->assertStatus(409);
    }

    public function test_stored_pdf_becomes_stale_and_problem_codes_stay_out_of_pdf_and_telegram(): void
    {
        config(['telegram.token' => 'TEST']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'document' => ['file_id' => 'F', 'file_unique_id' => 'U']]])]);
        $company = $this->company();
        $user = $this->user($company);
        $user->forceFill(['telegram_chat_id' => '555'])->save();
        $batch = $this->batch($user, 4);
        $template = LabelTemplate::default();

        $this->get(route('import-batches.labels', [$batch, 'template' => $template->id]))->assertOk();
        $this->assertSame(4, LabelPdf::sole()->labels);
        $this->assertTrue(app(LabelPdfStore::class)->parts($batch->fresh(), $template)[0]['ready']);

        $this->fakeApi(fn ($code, $i) => $i < 2 ? ['status' => 'WRITTEN_OFF'] : ['status' => 'RECEIVED']);
        $this->order($batch);
        $batch->refresh();
        $this->assertSame(1, $batch->aslbelgi_revision);

        $part = app(LabelPdfStore::class)->parts($batch, $template)[0];
        $this->assertFalse($part['ready']); // the stored 4-label PDF is stale
        $this->assertSame(2, $part['labels']);

        // Telegram PDF: re-rendered without the problem codes
        (new SendBatchPdfToTelegram($batch->id, $template->id, '555'))->handle(app(LabelPdfStore::class), app(\App\Domain\Telegram\TelegramApi::class), app(\App\Domain\Telegram\BotHandler::class));
        $pdf = LabelPdf::sole();
        $this->assertSame(2, $pdf->labels);
        $this->assertSame(1, $pdf->aslbelgi_revision);
        $this->assertSame(
            MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->whereNull('aslbelgi_problem')->pluck('id')->sort()->values()->all(),
            app(LabelPdfStore::class)->partCodes($batch, $part)->pluck('id')->sort()->values()->all()
        );
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendDocument'));

        // the same result again: problem set unchanged → revision unchanged, PDF stays valid
        $this->order($batch);
        $this->assertSame(1, $batch->fresh()->aslbelgi_revision);
        $this->assertTrue(app(LabelPdfStore::class)->parts($batch->fresh(), $template)[0]['ready']);
    }

    public function test_recheck_is_a_new_order_charged_again_with_a_warning(): void
    {
        $company = $this->company(balance: 100);
        $batch = $this->batch($this->user($company), 2);
        $this->fakeApi(fn () => ['status' => 'RECEIVED']);

        $this->order($batch);
        $first = AslbelgiCheck::sole();

        Livewire::test(ImportBatchIndex::class)->call('openCheck', $batch->id)
            ->assertSee(__('aslbelgi_check.already_checked', ['date' => $first->finished_at->format('d.m.Y H:i')]))
            ->call('orderCheck');

        $this->assertSame(2, AslbelgiCheck::count());
        $this->assertSame(60.0, $company->fresh()->balance);
        $this->get(route('import-batches.index'))->assertSee(__('aslbelgi_check.checked'));
    }

    public function test_company_without_key_sees_no_action_and_cannot_order(): void
    {
        $company = $this->company(key: false);
        $batch = $this->batch($this->user($company), 2);

        $this->get(route('import-batches.index'))->assertOk()->assertDontSee(__('aslbelgi_check.action'));
        $this->order($batch)->assertDispatched('notify', type: 'error');

        // an expired key cannot be used either
        $company->forceFill(['aslbelgi_api_key' => self::KEY, 'aslbelgi_api_key_expires_at' => now()->subDay()])->save();
        Livewire::test(ImportBatchIndex::class)->call('openCheck', $batch->id)->assertSee(__('aslbelgi_check.key_expired'))
            ->call('orderCheck')->assertDispatched('notify', type: 'error');

        $this->assertSame(0, AslbelgiCheck::count());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'xtrace'));
    }

    public function test_another_companys_batch_cannot_be_checked(): void
    {
        $company = $this->company();
        $batch = $this->batch($this->user($company), 2);
        $other = Company::create(['tin' => '300000999', 'name' => 'Other', 'is_active' => true]);
        $other->forceFill(['aslbelgi_api_key' => 'x' . self::KEY, 'balance' => 1000])->save();
        $this->actingAs($this->user($other, 'owner', 'x@t.uz'));

        Livewire::test(ImportBatchIndex::class)->call('openCheck', $batch->id)->assertStatus(404);
        $this->assertSame(0, AslbelgiCheck::count());
    }

    public function test_key_expiry_reminders_are_sent_once_per_threshold(): void
    {
        config(['telegram.token' => 'TEST']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        $company = $this->company();
        $company->forceFill(['aslbelgi_api_key_expires_at' => now()->addDays(14)->setTime(23, 59)])->save();
        $this->user($company)->forceFill(['telegram_chat_id' => '111', 'locale' => 'ru'])->save();
        $this->user($company, 'member', 'm@t.uz')->forceFill(['telegram_chat_id' => '222'])->save(); // not the owner: no reminder

        $sent = fn () => collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'sendMessage'))->map(fn ($pair) => $pair[0]->data());

        $this->artisan('aslbelgi:key-reminders')->assertSuccessful();
        $this->artisan('aslbelgi:key-reminders')->assertSuccessful();
        $this->assertCount(1, $sent());
        $this->assertSame('111', (string) $sent()->first()['chat_id']);
        $this->assertStringContainsString('14', $sent()->first()['text']);
        $this->assertStringContainsString('Срок API-ключа', $sent()->first()['text']); // owner's language

        $this->travel(11)->days(); // 3 days left
        $this->artisan('aslbelgi:key-reminders');
        $this->artisan('aslbelgi:key-reminders');
        $this->assertCount(2, $sent());

        $this->travel(3)->days(); // expiry day
        $this->artisan('aslbelgi:key-reminders');
        $this->travel(1)->days();
        $this->artisan('aslbelgi:key-reminders');
        $this->assertCount(3, $sent());
        $this->assertStringContainsString('⛔', $sent()->last()['text']);
        $this->assertKeyNeverLeaked($sent()->toJson());

        // a new key (new expiry date) starts its reminders again
        $company->forceFill(['aslbelgi_api_key_expires_at' => now()->addDays(10)])->save();
        $this->artisan('aslbelgi:key-reminders');
        $this->assertCount(4, $sent());
    }

    public function test_sweep_runs_a_check_no_worker_took(): void
    {
        $company = $this->company(balance: 100);
        $batch = $this->batch($this->user($company), 2);
        $this->fakeApi(fn () => ['status' => 'RECEIVED']);
        $check = app(\App\Domain\Billing\BalanceService::class)->orderAslbelgiCheck($batch, User::first(), 2, 10); // queued, never dispatched

        $this->artisan('aslbelgi:checks');
        $this->assertSame('queued', $check->fresh()->status); // too new: a worker may still take it
        $this->travel(3)->minutes();
        $this->artisan('aslbelgi:checks');
        $this->assertSame('done', $check->fresh()->status);
        $this->assertSame(80.0, $company->fresh()->balance);
    }
}
