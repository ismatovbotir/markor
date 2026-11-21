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
}
