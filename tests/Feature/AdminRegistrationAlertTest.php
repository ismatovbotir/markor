<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminRegistrationAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['telegram.token' => 'TEST', 'telegram.admin_chat' => '777']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'tasnif.soliq.uz/*' => Http::response(['success' => true, 'data' => []]), '*' => Http::response([], 404)]);
        User::create(['name' => 'Admin', 'email' => 'adm@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
    }

    public function test_admin_gets_a_telegram_alert_on_sign_up_and_on_company(): void
    {
        $this->post(route('register.post'), [
            'name' => 'Ali Valiyev', 'email' => 'ali@firma.uz', 'phone' => '+998901112233',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] == '777'
            && str_contains($r['text'], "Yangi foydalanuvchi") && str_contains($r['text'], 'ali@firma.uz') && str_contains($r['text'], '+998901112233'));

        \Livewire\Livewire::test(\App\Http\Livewire\Account\CompanySetup::class)
            ->set('tin', '309876543')->call('lookup')->set('company.name', 'Vali Savdo MChJ')->call('save');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], 'Vali Savdo MChJ') && str_contains($r['text'], '309876543'));
    }

    public function test_html_in_names_is_escaped_and_telegram_down_does_not_block_sign_up(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('down', 500), '*' => Http::response([], 404)]);
        $this->post(route('register.post'), [
            'name' => '<b>x</b>', 'email' => 'x@firma.uz', 'phone' => '+998901112234',
            'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertRedirect();
        $this->assertAuthenticated();
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains($r['text'], '&lt;b&gt;x&lt;/b&gt;'));
    }
}
