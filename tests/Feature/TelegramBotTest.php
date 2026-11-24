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

    private function update(array $message, string $lang = 'ru'): void
    {
        app(BotHandler::class)->handle(['message' => $message + ['chat' => ['id' => 555, 'type' => 'private'], 'from' => ['id' => 555, 'language_code' => $lang, 'username' => 'ali_tg']]]);
    }

    private function lastText(): string
    {
        return end($this->sent)['text'] ?? '';
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

    // ---------- linking: Start → number → (profile phone: linked) / (else: 6-digit cabinet code) ----------

    public function test_start_asks_for_the_number_and_the_own_profile_phone_links_at_once(): void
    {
        $user = $this->client('+998 90 175 40 01');

        $this->update(['text' => '/start']);
        $this->assertStringContainsString('номер', $this->lastText());              // Telegram app language for a new chat
        $this->assertTrue(end($this->sent)['reply_markup']['keyboard'][0][0]['request_contact']);

        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $link = TelegramLink::sole();
        $this->assertSame(['linked', '+998901754001', 'ali_tg'], [$link->status, $link->shared_phone, $link->tg_username]); // history for the admin
        $this->assertStringContainsString('CSV', $this->lastText());
    }

    public function test_someone_elses_contact_is_refused(): void
    {
        $user = $this->client('+998901754001');
        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 777]]);
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_unknown_number_asks_for_the_cabinet_code_which_links(): void
    {
        $user = $this->client('+998 90 111 22 33');
        $this->actingAs($user);
        $cabinet = Livewire::test(TelegramConnect::class)->call('start');
        $link = TelegramLink::sole();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $link->code);            // made on the server
        $cabinet->assertSee($link->code)->assertDontSee('<img', false);              // no QR any more

        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertStringContainsString('6-значный код', $this->lastText());
        $this->assertNull($user->fresh()->telegram_chat_id);

        $this->update(['text' => substr($link->code, 0, 3) . ' ' . substr($link->code, 3)]); // typed with a space
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $this->assertSame(['linked', '+998901754001'], [$link->fresh()->status, $link->fresh()->shared_phone]);
        $cabinet->call('tick')->assertSee(__('telegram.success'));
    }

    public function test_company_phone_or_a_number_of_several_accounts_needs_the_code(): void
    {
        $user = $this->client('+998 90 111 22 33');
        $user->company->update(['phone' => '+998901754001']);
        $this->update(['contact' => ['phone_number' => '998901754001', 'user_id' => 555]]);
        $this->assertNull($user->fresh()->telegram_chat_id);

        $this->client('+998901112233'); // a second account with the same phone
        $this->update(['contact' => ['phone_number' => '998901112233', 'user_id' => 555]]);
        $this->assertNull($user->fresh()->telegram_chat_id);
        $this->assertStringContainsString('6-значный код', $this->lastText());
    }

    public function test_code_lives_30_seconds_then_a_new_one_and_the_old_one_works_a_moment_longer(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        $cabinet = Livewire::test(TelegramConnect::class)->call('start');
        $old = TelegramLink::sole()->code;

        $cabinet->call('tick');
        $this->assertSame($old, TelegramLink::sole()->code);                         // still valid

        $this->travel(31)->seconds();
        $cabinet->call('tick');
        $new = TelegramLink::sole()->code;
        $this->assertNotSame($old, $new);
        $cabinet->assertSee($new);

        $this->travel(20)->seconds();                                                 // the old code's grace is over
        $this->update(['text' => $old === $new ? '000000' : $old]);
        $this->assertNull($user->fresh()->telegram_chat_id);

        $cabinet->call('tick');                                                       // ran out again → renewed
        $this->update(['text' => TelegramLink::sole()->code]);
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
    }

    public function test_a_code_typed_just_as_it_changes_is_accepted(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        $cabinet = Livewire::test(TelegramConnect::class)->call('start');
        $old = TelegramLink::sole()->code;
        $this->travel(31)->seconds();
        $cabinet->call('tick');

        $this->update(['text' => $old]);
        $this->assertSame('555', $user->fresh()->telegram_chat_id);
    }

    public function test_the_window_stops_making_codes_after_ten_minutes(): void
    {
        $this->actingAs($this->client());
        $cabinet = Livewire::test(TelegramConnect::class)->call('start');
        $this->travel(TelegramConnect::WINDOW * 60 + 31)->seconds();
        $cabinet->call('tick')->assertSee(__('telegram.new_code'));
        $this->assertSame(['expired', null], [TelegramLink::sole()->status, TelegramLink::sole()->code]);
    }

    public function test_five_wrong_codes_pause_the_chat(): void
    {
        $user = $this->client();
        $this->actingAs($user);
        Livewire::test(TelegramConnect::class)->call('start');
        $right = TelegramLink::sole()->code;
        $wrong = $right === '123456' ? '654321' : '123456';

        foreach (range(1, BotHandler::CODE_TRIES) as $i) {
            $this->update(['text' => $wrong]);
        }
        $this->update(['text' => $right]);                                            // even the right code waits now
        $this->assertNull($user->fresh()->telegram_chat_id);
        $this->assertStringContainsString('10 мин', $this->lastText());
    }

    // ---------- super admin: connect an old unmatched attempt by hand ----------

    private function unmatched(User $user): TelegramLink
    {
        return TelegramLink::create(['user_id' => $user->id, 'token' => 'tok', 'chat_id' => '555', 'shared_phone' => '+998901754001',
            'status' => 'phone_mismatch', 'expires_at' => now()]);
    }

    public function test_super_admin_links_an_unmatched_attempt_by_hand(): void
    {
        $user = $this->client('+998 90 111 22 33');
        $link = $this->unmatched($user);

        $admin = User::create(['name' => 'Admin', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->set('tab', 'unmatched')
            ->assertSee('+998901754001')->assertSeeHtml('wire:click="link(' . $link->id . ')"')
            ->call('link', $link->id);

        $this->assertSame('555', $user->fresh()->telegram_chat_id);
        $this->assertSame('admin_linked', $link->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'telegram.admin_linked', 'entity_id' => $user->id, 'user_id' => $admin->id]);
        $this->assertTrue(collect($this->sent)->contains(fn ($m) => str_contains($m['text'] ?? '', 'администратор')));

        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->call('link', $link->id); // only once
        $this->assertSame('admin_linked', $link->fresh()->status);
        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->set('tab', 'matched')->assertSee(__('tg_admin.status.admin_linked'));
    }

    public function test_only_the_super_admin_can_link_by_hand(): void
    {
        $user = $this->client();
        $link = $this->unmatched($user);
        $this->actingAs($user);

        Livewire::test(\App\Http\Livewire\Admin\TelegramPage::class)->call('link', $link->id)->assertForbidden();
        $this->assertNull($user->fresh()->telegram_chat_id);
    }
}
