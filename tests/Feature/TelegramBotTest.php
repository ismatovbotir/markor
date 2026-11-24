<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Telegram\BotHandler;
use App\Domain\Telegram\TelegramLink;
use App\Http\Livewire\Account\TelegramConnect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array> messages the bot sent */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.token' => 'TEST', 'contact.telegram_bot' => 'markor_bot']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'api.telegram.org/botTEST/getFile')) {
                return Http::response(['ok' => true, 'result' => ['file_path' => 'docs/codes.csv']]);
            }
            if (str_contains($request->url(), 'api.telegram.org/file/botTEST/')) {
                return Http::response("0104780000000097" . "21" . str_repeat('A', 20) . "\x1D91ABCD\x1D92" . str_repeat('x', 44) . "\n");
            }
            if (str_contains($request->url(), 'api.telegram.org')) {
                $this->sent[] = $request->data();
                return Http::response(['ok' => true, 'result' => []]);
            }
            return str_contains($request->url(), 'tasnif') ? Http::response(['success' => true, 'data' => []]) : null;
        });
    }

    private function client(string $phone = '+998 90 111 22 33'): User
    {
        $company = Company::create(['tin' => '30' . random_int(1000000, 9999999), 'name' => 'Texno']);

        return User::create(['company_id' => $company->id, 'name' => 'Ali', 'email' => uniqid() . '@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'ru', 'phone' => $phone, 'is_active' => true]);
    }

    private function update(array $message): void
    {
        app(BotHandler::class)->handle(['message' => $message + ['chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555]]]);
    }

    private function lastText(): string
    {
        return end($this->sent)['text'] ?? '';
    }

    public function test_full_linking_flow_qr_phone_code(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        Livewire::test(TelegramConnect::class)->call('start')->assertSee('t.me/markor_bot?start=');
        $link = TelegramLink::firstOrFail();

        $this->update(['text' => "/start {$link->token}"]);
        $this->assertStringContainsString('номер', $this->lastText());           // asks for the phone, in the cabinet language
        $this->assertTrue(end($this->sent)['reply_markup']['keyboard'][0][0]['request_contact']);

        $this->update(['contact' => ['phone_number' => '998901112233', 'user_id' => 555]]);
        $link->refresh();
        $this->assertSame('phone_ok', $link->status);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $link->code);
        Livewire::test(TelegramConnect::class)->set('linkId', $link->id)->set('open', true)->assertSee($link->code); // the cabinet shows the code

        $this->update(['text' => '000000' === $link->code ? '111111' : '000000']);
        $this->assertStringContainsString('Неверный', $this->lastText());

        $this->update(['text' => $link->code]);
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $this->assertSame('linked', $link->fresh()->status);
        $this->assertStringContainsString('CSV', $this->lastText());               // brief instructions sent
    }

    public function test_phone_mismatch_and_foreign_contact_are_refused(): void
    {
        $user = $this->client('+998 90 111 22 33');
        $link = TelegramLink::create(['user_id' => $user->id, 'token' => 'tok', 'expires_at' => now()->addMinutes(15)]);
        $this->update(['text' => '/start tok']);

        $this->update(['contact' => ['phone_number' => '998901112233', 'user_id' => 777]]); // someone else's contact
        $this->assertSame('started', $link->fresh()->status);

        $this->update(['contact' => ['phone_number' => '998909999999', 'user_id' => 555]]);
        $this->assertSame('phone_mismatch', $link->fresh()->status);
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_linked_client_sends_csv_gets_status_and_pdf_button(): void
    {
        Queue::fake();
        $user = $this->client();
        $user->update(['telegram_chat_id' => '555']);

        $this->update(['document' => ['file_id' => 'F1', 'file_name' => 'partiya.csv', 'file_size' => 200]]);
        $batch = ImportBatch::withoutGlobalScope('owner')->firstOrFail();
        $this->assertSame('telegram', $batch->source);
        $this->assertSame('pending', $batch->status);
        $this->assertStringContainsString('partiya.csv', $this->lastText());

        $this->processBatches();
        $done = collect($this->sent)->first(fn ($m) => str_contains($m['text'] ?? '', 'загружен'));
        $this->assertNotNull($done, 'load result is sent');
        $this->assertStringContainsString('1 × 9', $done['text'], 'count × price in the message');
        $this->assertStringContainsString('Скидка 100%', $done['text']);
        $callback = $done['reply_markup']['inline_keyboard'][0][0]['callback_data'];
        $this->assertStringStartsWith("pdf:{$batch->id}:", $callback);

        app(BotHandler::class)->handle(['callback_query' => ['id' => 'q1', 'data' => $callback, 'message' => ['chat' => ['id' => 555]]]]);
        Queue::assertPushed(\App\Jobs\SendBatchPdfToTelegram::class);

        $this->update(['text' => '/status']);
        $this->assertStringContainsString('partiya.csv', $this->lastText());
    }

    public function test_unlinked_chat_cannot_upload(): void
    {
        $this->update(['document' => ['file_id' => 'F1', 'file_name' => 'x.csv', 'file_size' => 10]]);
        $this->assertSame(0, ImportBatch::withoutGlobalScope('owner')->count());
        $this->assertStringContainsString('Markor', $this->lastText());
    }

    public function test_same_file_name_is_refused_for_the_company(): void
    {
        $user = $this->client();
        $user->update(['telegram_chat_id' => '555']);

        $this->update(['document' => ['file_id' => 'F1', 'file_name' => 'partiya.csv', 'file_size' => 200]]);
        $this->update(['document' => ['file_id' => 'F2', 'file_name' => 'PARTIYA.csv', 'file_size' => 200]]);
        $this->assertSame(1, ImportBatch::withoutGlobalScope('owner')->count(), 'second file with the same name is not loaded');
        $this->assertStringContainsString('#' . ImportBatch::withoutGlobalScope('owner')->value('id'), $this->lastText());
    }

    public function test_super_admin_links_an_unmatched_attempt_by_hand(): void
    {
        $user = $this->client('+998 90 111 22 33');
        $link = TelegramLink::create(['user_id' => $user->id, 'token' => 'tok', 'expires_at' => now()->addMinutes(15)]);
        $this->update(['text' => '/start tok']);
        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertSame('phone_mismatch', $link->fresh()->status);

        $admin = User::create(['name' => 'Admin', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->set('tab', 'unmatched')
            ->assertSee('+998901754001')->assertSeeHtml('wire:click="link(' . $link->id . ')"')
            ->call('link', $link->id);

        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $this->assertSame('admin_linked', $link->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'telegram.admin_linked', 'entity_id' => $user->id, 'user_id' => $admin->id]);
        $this->assertTrue(collect($this->sent)->contains(fn ($m) => str_contains($m['text'] ?? '', 'администратор'))); // the client is told, in his language

        // only unmatched attempts, only once
        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->call('link', $link->id);
        $this->assertSame('admin_linked', $link->fresh()->status);
        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->set('tab', 'matched')->assertSee(__('tg_admin.status.admin_linked'));
    }

    public function test_only_the_super_admin_can_link_by_hand(): void
    {
        $user = $this->client();
        $link = TelegramLink::create(['user_id' => $user->id, 'token' => 'tok2', 'chat_id' => '555', 'status' => 'phone_mismatch', 'expires_at' => now()->addMinutes(15)]);
        $this->actingAs($user);

        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->call('link', $link->id)->assertForbidden();
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_number_without_the_qr_start_continues_with_the_open_qr_of_that_phone(): void
    {
        $user = $this->client('+998901754001');
        $link = TelegramLink::create(['user_id' => $user->id, 'token' => 'tok', 'expires_at' => now()->addMinutes(15)]); // QR shown, START never sent

        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $link->refresh();
        $this->assertSame(['phone_ok', '555'], [$link->status, $link->chat_id]);   // goes on to the 6-digit code
        $this->update(['text' => $link->code]);
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
    }

    public function test_old_send_number_button_without_an_open_qr_says_scan_first(): void
    {
        $user = $this->client('+998901754001');
        TelegramLink::create(['user_id' => $user->id, 'token' => 'old', 'chat_id' => '555', 'status' => 'phone_mismatch', 'expires_at' => now()->addMinutes(15)]);

        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertStringContainsString('START', $this->lastText());
        $this->assertTrue(end($this->sent)['reply_markup']['remove_keyboard'] ?? false); // the stale button goes away
        $this->assertNull($user->fresh()->telegram_chat_id);

        // two open QRs with the same number (company phone shared): never guessed
        $other = $this->client('+998 90 175 40 01');
        TelegramLink::create(['user_id' => $user->id, 'token' => 'a', 'expires_at' => now()->addMinutes(15)]);
        TelegramLink::create(['user_id' => $other->id, 'token' => 'b', 'expires_at' => now()->addMinutes(15)]);
        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertStringContainsString('START', $this->lastText());
        $this->assertSame(0, TelegramLink::where('status', 'phone_ok')->count());
    }
}
