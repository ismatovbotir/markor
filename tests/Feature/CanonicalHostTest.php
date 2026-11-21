<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_bare_domain_redirects_to_the_app_url_host(): void
    {
        config(['app.url' => 'https://www.markor.uz']);

        $this->get('https://markor.uz/marketplace/ribbon-wax-110?lang=ru')
            ->assertStatus(301)->assertRedirect('https://www.markor.uz/marketplace/ribbon-wax-110?lang=ru');
        $this->get('https://www.markor.uz/')->assertOk();
        $this->get('https://other.example/')->assertOk(); // unrelated hosts (tests, local) are left alone
        $this->postJson('https://markor.uz/offer-request', [])->assertStatus(422); // forms are never redirected
    }
}
