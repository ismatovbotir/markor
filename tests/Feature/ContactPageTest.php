<?php

namespace Tests\Feature;

use App\Domain\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(['contact.phone' => '+998 90 111 22 33', 'contact.telegram' => 'testshop', 'contact.email' => 'shop@example.uz',
            'contact.wechat' => 'SomeWeChatId', 'contact.wechat_link' => 'https://u.wechat.com/AbCdEf123']);
    }

    public function test_contact_page_lists_contacts_and_wechat_only_in_chinese(): void
    {
        $this->get('/contact?lang=uz')->assertOk()
            ->assertSee('<h1', false)->assertSee('+998 90 111 22 33')->assertSee('t.me/testshop', false)->assertSee('mailto:shop@example.uz', false)
            ->assertSee('"@type":"ContactPage"', false)
            ->assertDontSee('/images/wechat-qr.jpg', false);
        $this->get('/contact?lang=zh')->assertOk()->assertSee('/images/wechat-qr.jpg', false)->assertDontSee('SomeWeChatId');
        $this->get(route('sitemap'))->assertSee(route('contact'), false);
    }

    public function test_every_public_page_has_the_same_footer_and_the_contacts_link(): void
    {
        foreach (['/', '/label-design', '/guide', '/uzbekistan-marking', '/contact'] as $url) {
            $html = $this->get($url . '?lang=zh')->assertOk()->getContent();
            $this->assertStringContainsString('mailto:shop@example.uz', $html, "footer on {$url}");
            $this->assertGreaterThanOrEqual(2, substr_count($html, route('contact') . '?lang=zh'), "contacts in header + footer on {$url}");
            $this->assertStringContainsString('/images/wechat-qr.jpg', $html, "WeChat in the Chinese footer on {$url}");
        }
        $this->assertStringNotContainsString('/images/wechat-qr.jpg', $this->get('/?lang=ru')->getContent());
    }
}
