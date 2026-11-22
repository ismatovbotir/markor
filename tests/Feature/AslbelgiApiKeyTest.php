<?php

namespace Tests\Feature;

use App\Domain\Companies\Company;
use App\Http\Livewire\Account\AslbelgiApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AslbelgiApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'c09d906f-1111-4ae5-2222-61c0934bcd59';

    private function company(): Company
    {
        return Company::create(['tin' => '307797292', 'name' => 'Test MChJ', 'is_active' => true]);
    }

    private function user(Company $c, string $role): User
    {
        return User::create(['name' => $role, 'email' => "$role@t.uz", 'password' => 'secret123', 'role' => 'user', 'locale' => 'uz', 'is_active' => true,
            'company_id' => $c->id, 'company_role' => $role]);
    }

    public function test_owner_saves_a_checked_key_stored_encrypted_and_hidden(): void
    {
        Http::fake(['xtrace.aslbelgisi.uz/public/api/v1/party/parties/307797292/api-keys/check' => Http::response(['isTinCorrect' => true, 'expiresOn' => '2027-01-15T23:59:59Z'])]);
        $c = $this->company();
        $this->actingAs($this->user($c, 'owner'));

        Livewire::test(AslbelgiApiKey::class)->set('apiKey', ' ' . self::KEY . ' ')->call('save')->assertHasNoErrors()
            ->assertSet('apiKey', '')->assertSee('cd59')->assertDontSee(self::KEY);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer ' . self::KEY) && $r->method() === 'GET');
        $c->refresh();
        $this->assertSame(self::KEY, $c->aslbelgi_api_key);
        $this->assertSame('2027-01-15', $c->aslbelgi_api_key_expires_at->toDateString());
        $this->assertStringNotContainsString(self::KEY, (string) DB::table('companies')->value('aslbelgi_api_key')); // encrypted at rest
        $this->assertArrayNotHasKey('aslbelgi_api_key', $c->toArray());
        $this->get(route('company.setup'))->assertOk()->assertSee(__('aslbelgi_key.title'))->assertDontSee(self::KEY);
    }

    public function test_key_of_another_company_or_rejected_key_is_not_saved(): void
    {
        $c = $this->company();
        $this->actingAs($this->user($c, 'owner'));

        Http::fake(['*' => Http::response(['isTinCorrect' => false])]);
        Livewire::test(AslbelgiApiKey::class)->set('apiKey', self::KEY)->call('save')->assertHasErrors('apiKey');

        Http::fake(['*' => Http::response([['code' => 'access-denied']], 401)]);
        Livewire::test(AslbelgiApiKey::class)->set('apiKey', self::KEY)->call('save')->assertHasErrors('apiKey');

        $this->assertNull($c->fresh()->aslbelgi_api_key);
    }

    public function test_only_the_owner_has_access(): void
    {
        Http::fake();
        $c = $this->company();
        $c->forceFill(['aslbelgi_api_key' => self::KEY])->save();

        $this->actingAs($this->user($c, 'member'));
        Livewire::test(AslbelgiApiKey::class)->assertForbidden();
        $this->assertStringNotContainsString(__('aslbelgi_key.title'), $this->get(route('company.setup'))->getContent());

        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
        $this->get(route('admin.companies.show', $c))->assertOk()->assertDontSee(self::KEY);
        Http::assertNothingSent();
    }
}
