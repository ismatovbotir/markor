<?php

namespace Tests\Feature;

use App\Domain\Reporting\AuditLog;
use App\Domain\Settings\Setting;
use App\Http\Livewire\Admin\TelegramBotProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramBotProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.token' => 'TEST']);
        $this->admin = User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    /** Fake Bot API: get* answer per language_code; set* answer ok (or what $override returns). */
    private function fakeTelegram(?callable $override = null): void
    {
        Http::fake(function (Request $request) use ($override) {
            $method = basename(parse_url($request->url(), PHP_URL_PATH));
            $lang = $request['language_code'] ?? 'default';
            if ($override && ($response = $override($method, $lang, $request))) {
                return $response;
            }

            return Http::response(['ok' => true, 'result' => match ($method) {
                'getMe'                 => ['id' => 42, 'is_bot' => true, 'first_name' => 'Markor', 'username' => 'markor_bot'],
                'getMyName'             => ['name' => "Name $lang"],
                'getMyDescription'      => ['description' => $lang === 'zh' ? '' : "Description $lang"],
                'getMyShortDescription' => ['short_description' => "Short $lang"],
                'getMyCommands'         => $lang === 'ru' ? [] : [['command' => 'start', 'description' => "Start $lang"], ['command' => 'status', 'description' => "Status $lang"]],
                default                 => true,
            }]);
        });
    }

    private function calls(): array
    {
        return Http::recorded()->map(fn ($pair) => [basename(parse_url($pair[0]->url(), PHP_URL_PATH)), $pair[0]['language_code'] ?? null, $pair[0]->data()])->all();
    }

    public function test_load_from_telegram_fills_the_form_per_language(): void
    {
        $this->fakeTelegram();

        $c = Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)->call('loadFromTelegram')
            ->assertSet('form.name.default', 'Name default')
            ->assertSet('form.name.uz', 'Name uz')
            ->assertSet('form.name.zh', 'Name zh')
            ->assertSet('form.description.ru', 'Description ru')
            ->assertSet('form.description.zh', '')
            ->assertSet('form.short.uz', 'Short uz')
            ->assertSet('form.commands.ru', [])
            ->assertSet('form.commands.uz', [['command' => 'start', 'description' => 'Start uz'], ['command' => 'status', 'description' => 'Status uz']])
            ->assertSee('Telegramdagi bilan bir xil');

        $this->assertCount(16, $this->calls());   // 4 get* × 4 languages
        $this->assertSame('Name ru', Setting::json(TelegramBotProfile::SENT_KEY)['name']['ru']);
        $this->assertSame('Name ru', Setting::json(TelegramBotProfile::DRAFT_KEY)['name']['ru']);
        $this->assertFalse($c->instance()->unsent());
    }

    public function test_load_asks_before_overwriting_unsent_edits(): void
    {
        $this->fakeTelegram();

        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->set('form.name.uz', 'Mening botim')
            ->call('loadFromTelegram')
            ->assertSet('confirmLoad', true)
            ->assertSet('form.name.uz', 'Mening botim')
            ->call('loadFromTelegram', true)
            ->assertSet('confirmLoad', false)
            ->assertSet('form.name.uz', 'Name uz');
    }

    public function test_draft_survives_a_reload_and_shows_unsent_state(): void
    {
        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->set('form.description.ru', 'Описание')
            ->set('form.commands.default', [['command' => '/Status', 'description' => 'x']])
            ->set('form.commands.default.0.command', '/Status')
            ->assertSet('form.commands.default.0.command', 'status');

        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->assertSet('form.description.ru', 'Описание')
            ->assertSee('O\'zgarishlar Telegramga yuborilmagan');
    }

    public function test_send_only_commands_for_uz_calls_only_set_my_commands_with_uz(): void
    {
        $this->fakeTelegram();

        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->call('suggest')
            ->call('prepareSend', 'commands', 'current', 'uz')
            ->assertSet('sendPlan', ['option' => 'commands', 'parts' => ['commands'], 'langs' => ['uz']])
            ->assertSee('Telegramga yuborilsinmi?')
            ->call('send')
            ->assertSet('sendPlan', null)
            ->assertHasNoErrors();

        $calls = $this->calls();
        $this->assertCount(1, $calls);
        [$method, $lang, $data] = $calls[0];
        $this->assertSame('setMyCommands', $method);
        $this->assertSame('uz', $lang);
        $this->assertSame(['start', 'status'], array_column($data['commands'], 'command'));
        $this->assertSame(1, AuditLog::where('action', 'telegram.bot_profile.sent')->count());
    }

    public function test_send_all_calls_every_method_for_all_four_languages(): void
    {
        $this->fakeTelegram();

        $c = Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->call('suggest')
            ->set('form.commands.zh', [])                          // empty list → the zh commands are removed
            ->call('prepareSend', 'all', 'all', 'uz')
            ->call('send');

        $calls = $this->calls();
        $this->assertCount(16, $calls);
        foreach ([null, 'uz', 'ru', 'zh'] as $lang) {
            $methods = collect($calls)->where(1, $lang)->pluck(0)->all();
            $this->assertSame(['setMyName', 'setMyDescription', 'setMyShortDescription', $lang === 'zh' ? 'deleteMyCommands' : 'setMyCommands'], $methods, "lang $lang");
        }
        $default = collect($calls)->first(fn ($c) => $c[0] === 'setMyName' && $c[1] === null);
        $this->assertArrayNotHasKey('language_code', $default[2]);
        $this->assertSame('Markor', $default[2]['name']);
        $this->assertFalse($c->instance()->unsent());
        $c->assertSee('Telegramdagi bilan bir xil');
    }

    public function test_telegram_error_is_shown_and_the_rest_still_runs(): void
    {
        $this->fakeTelegram(fn ($method) => $method === 'setMyName'
            ? Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 3600', 'parameters' => ['retry_after' => 3600]], 429)
            : null);

        $c = Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->call('suggest')
            ->call('prepareSend', 'all', 'all', 'default')
            ->call('send')
            ->assertSee('Too Many Requests: retry after 3600')
            ->assertSee('retry_after 3600 s');

        $calls = collect($this->calls());
        $this->assertSame(1, $calls->where(0, 'setMyName')->count());          // later names skipped after the 429
        $this->assertSame(4, $calls->where(0, 'setMyDescription')->count());
        $this->assertSame(4, $calls->where(0, 'setMyCommands')->count());

        $results = collect($c->get('results'));
        $this->assertSame(3, $results->where('skipped', true)->count());
        $this->assertSame(12, $results->where('ok', true)->count());
        $this->assertTrue($c->instance()->unsent('uz'));                         // the name was not sent

        $log = AuditLog::where('action', 'telegram.bot_profile.sent')->sole();
        $this->assertStringNotContainsString('TEST', json_encode($log->payload));
        $this->assertSame('telegram_bot', $log->entity_type);
    }

    public function test_validation_mirrors_telegram_limits(): void
    {
        $this->fakeTelegram();

        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->set('form.name.ru', str_repeat('я', 65))
            ->set('form.description.uz', str_repeat('a', 513))
            ->set('form.short.zh', str_repeat('字', 121))
            ->set('form.commands.default', [
                ['command' => 'bad-cmd', 'description' => 'x'],
                ['command' => str_repeat('a', 33), 'description' => 'x'],
                ['command' => 'ok', 'description' => str_repeat('d', 257)],
                ['command' => 'nodesc', 'description' => ''],
                ['command' => '', 'description' => ''],              // fully empty: dropped, not an error
            ])
            ->call('prepareSend', 'all', 'all', 'default')
            ->assertHasErrors(['form.name.ru', 'form.description.uz', 'form.short.zh',
                'form.commands.default.0.command', 'form.commands.default.1.command',
                'form.commands.default.2.description', 'form.commands.default.3.description'])
            ->assertSet('sendPlan', null)
            ->assertCount('form.commands.default', 4)
            ->assertDispatched('bot-profile-errors');

        // limits are exact: 64 / 512 / 120 pass
        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->set('form.name.ru', str_repeat('я', 64))
            ->set('form.description.uz', str_repeat('a', 512))
            ->set('form.short.zh', str_repeat('字', 120))
            ->set('form.commands.default', array_fill(0, 101, ['command' => 'a', 'description' => 'b']))
            ->call('prepareSend', 'all', 'all', 'default')
            ->assertHasNoErrors(['form.name.ru', 'form.description.uz', 'form.short.zh'])
            ->assertHasErrors(['form.commands.default']);

        Http::assertNothingSent();
    }

    public function test_suggestion_uses_only_commands_the_bot_handles(): void
    {
        $c = Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)->assertSee('Tayyor matnlarni qo\'yish')->call('suggest');
        foreach (TelegramBotProfile::langs() as $l) {
            $this->assertSame(['start', 'status'], array_column($c->get("form.commands.$l"), 'command'));
            $this->assertLessThanOrEqual(512, mb_strlen($c->get("form.description.$l")));
            $this->assertLessThanOrEqual(120, mb_strlen($c->get("form.short.$l")));
        }
        $this->assertStringContainsString('Отправьте', $c->get('form.short.ru'));
    }

    public function test_settings_page_shows_the_card_and_a_disabled_state_without_token(): void
    {
        Http::fake();
        $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk()->assertSee('Bot profili (Telegramda)')->assertSee('bot-avatar-640.png');

        config(['telegram.token' => null]);
        Livewire::actingAs($this->admin)->test(TelegramBotProfile::class)
            ->assertSee('Bot sozlanmagan')
            ->assertDontSee('Telegramga yuborish')
            ->call('loadFromTelegram');
        Http::assertNothingSent();
        $this->assertFileExists(public_path('images/bot-avatar-640.png'));
    }

    public function test_non_admin_is_forbidden(): void
    {
        $user = User::create(['name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);

        Livewire::actingAs($user)->test(TelegramBotProfile::class)->assertForbidden();
        $this->actingAs($user)->get(route('admin.settings'))->assertForbidden();
    }
}
