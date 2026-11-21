<?php

namespace Tests\Feature;

use App\Domain\Settings\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChinaLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_chinese_landing_page_with_seo_faq_and_wechat(): void
    {
        Setting::put(['contact.wechat' => 'BotirIsmatov']);
        $html = $this->get('/uzbekistan-marking?lang=zh')->assertOk()
            ->assertSee('<html lang="zh-Hans">', false)
            ->assertSee('<title>' . e(__('seo.china.title', [], 'zh')) . '</title>', false)
            ->assertSee(e(__('china.h1', [], 'zh')), false)
            ->assertDontSee('BotirIsmatov')
            ->assertSee('<meta name="applicable-device" content="pc,mobile">', false)->getContent();
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->get('/uzbekistan-marking?lang=ru')->assertOk()->assertSee(e(__('china.h1', [], 'ru')), false);
        $this->get(route('sitemap'))->assertSee(route('china'), false);
    }

    public function test_register_is_an_icon_button_and_chinese_menu_links_the_page(): void
    {
        $this->get('/?lang=zh')->assertOk()
            ->assertSee('aria-label="' . e(__('auth.register', [], 'zh')) . '"', false)
            ->assertSee(route('china') . '?lang=zh', false);
    }

    public function test_baidu_verification_tag(): void
    {
        Setting::put(['seo.baidu' => 'codeva-AbCd1234']);
        $this->get('/uzbekistan-marking')->assertSee('<meta name="baidu-site-verification" content="codeva-AbCd1234">', false);
    }

    public function test_wechat_with_qr_code_and_link(): void
    {
        Setting::put(['contact.wechat' => 'BotirIsmatov', 'contact.wechat_link' => 'https://u.wechat.com/AbCdEf123']);
        $this->get('/uzbekistan-marking?lang=zh')->assertOk()
            ->assertDontSee('BotirIsmatov')
            ->assertSee('href="https://u.wechat.com/AbCdEf123"', false)
            ->assertSee('src="/images/wechat-qr.jpg"', false); // the owner's own WeChat QR image
    }

    public function test_wechat_only_on_the_chinese_version(): void
    {
        Setting::put(['contact.wechat' => 'BotirIsmatov', 'contact.wechat_link' => 'https://u.wechat.com/AbCdEf123']);
        $this->get('/uzbekistan-marking?lang=zh')->assertSee('/images/wechat-qr.jpg', false);
        $this->get('/uzbekistan-marking?lang=ru')->assertDontSee('/images/wechat-qr.jpg', false)->assertDontSee('u.wechat.com', false);
        $this->get('/uzbekistan-marking?lang=uz')->assertDontSee('/images/wechat-qr.jpg', false);
    }
}
