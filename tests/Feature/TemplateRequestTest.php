<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Domain\Printing\Models\TemplateRequest;
use App\Http\Livewire\Admin\TemplateRequestIndex;
use App\Http\Livewire\Printing\TemplateCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_orders_a_template_admin_gets_it_in_telegram_and_answers(): void
    {
        config(['telegram.token' => 'TEST', 'telegram.admin_chat' => '777']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]), '*' => Http::response([], 404)]);
        $admin = User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $company = Company::create(['tin' => '302000001', 'name' => 'Etiket MChJ']);
        $client = User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'Ali', 'email' => 'ali@t.uz', 'phone' => '+998901112233',
            'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true, 'telegram_chat_id' => '555']);

        $this->actingAs($client);
        Livewire::test(TemplateCatalog::class)->call('openOrder')
            ->set('order.width_mm', 58)->set('order.height_mm', 40)->set('order.columns', 2)->set('order.gap_mm', 2)->set('order.comment', 'Xprinter, nom va GTIN')
            ->call('sendOrder')->assertHasNoErrors()->assertSet('ordering', false)->assertSee('58×40 mm');

        $request = TemplateRequest::firstOrFail();
        $this->assertSame([58, 40, 2, 'new'], [$request->width_mm, $request->height_mm, $request->columns, $request->status]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] == '777' && str_contains($r['text'], 'Etiket MChJ') && str_contains($r['text'], 'Xprinter, nom va GTIN'));

        $this->actingAs($admin);
        Livewire::test(TemplateRequestIndex::class)->assertSee('Etiket MChJ')->call('open', $request->id)->set('note', 'Shablon qo\'shildi')->call('answer', 'done');
        $this->assertSame('done', $request->fresh()->status);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] == '555' && str_contains($r['text'], 'Shablon qo'));
    }

    public function test_client_cannot_open_the_admin_page_and_requests_are_limited(): void
    {
        $company = Company::create(['tin' => '302000002', 'name' => 'B']);
        $client = User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'B', 'email' => 'b@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($client)->get(route('admin.template-requests'))->assertForbidden();

        for ($i = 0; $i < 5; $i++) {
            TemplateRequest::create(['company_id' => $company->id, 'width_mm' => 30, 'height_mm' => 20]);
        }
        Livewire::test(TemplateCatalog::class)->call('openOrder')->set('order.width_mm', 30)->set('order.height_mm', 20)->call('sendOrder')->assertHasErrors('order.comment');
    }

    public function test_password_is_changed_in_a_window(): void
    {
        $company = Company::create(['tin' => '302000003', 'name' => 'C']);
        $user = User::create(['company_id' => $company->id, 'company_role' => 'owner', 'name' => 'C', 'email' => 'c@t.uz', 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($user);
        Livewire::test(\App\Http\Livewire\Account\ProfilePage::class)
            ->assertDontSee('autocomplete="current-password"', false)          // no password fields until the button
            ->call('openPassword')->assertSet('passwordModal', true)->assertSee('autocomplete="current-password"', false)
            ->set('current_password', 'secret123')->set('password', 'new-secret-9')->set('password_confirmation', 'new-secret-9')
            ->call('savePassword')->assertHasNoErrors()->assertSet('passwordModal', false);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secret-9', $user->fresh()->password));
    }
}
