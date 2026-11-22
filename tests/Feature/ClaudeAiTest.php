<?php

namespace Tests\Feature;

use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PaymentRequiredException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Domain\Ai\AiUsage;
use App\Domain\Ai\Claude;
use App\Domain\Ai\ProductNamer;
use App\Domain\Catalog\Models\Product;
use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Http\Livewire\Admin\OfferCategoryManager;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Claude without real calls: a fake that answers from a queue or throws SDK errors. */
class FakeClaude extends Claude
{
    public array $queue = [];
    public array $sent = [];

    protected function send(array $params): object
    {
        $this->sent[] = $params;
        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return (object) [
            'stopReason' => 'end_turn', 'stopDetails' => null,
            'usage' => (object) ['inputTokens' => 1000, 'outputTokens' => 200, 'cacheReadInputTokens' => 0, 'cacheCreationInputTokens' => 0],
            'content' => [(object) ['type' => 'text', 'text' => json_encode($next)]],
        ];
    }
}

/** Model list without the API. */
class FakeResolver extends \App\Domain\Ai\ModelResolver
{
    public array $models = [];

    protected function activeModels(): iterable
    {
        return $this->models;
    }
}

class ClaudeAiTest extends TestCase
{
    use RefreshDatabase;

    private FakeClaude $claude;
    private FakeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.api_key' => 'sk-test', 'ai.enabled' => true]);
        $this->claude = new FakeClaude();
        $this->app->instance(Claude::class, $this->claude);
        $this->resolver = new FakeResolver();
        $this->resolver->models = [
            ['id' => 'claude-haiku-5-5', 'line' => 'haiku', 'created' => 1000],
            ['id' => 'claude-sonnet-5-5', 'line' => 'sonnet', 'created' => 1000],
        ];
        $this->app->instance(\App\Domain\Ai\ModelResolver::class, $this->resolver);
    }

    private function product(string $classifierName, ?string $name = null): Product
    {
        return Product::withoutGlobalScope('owner')->create([
            'gtin' => '0690697431' . random_int(1000, 9999), 'name_uz' => $name ?? \App\Domain\Catalog\Services\GtinLookup::shortName($classifierName),
            'attributes' => ['classifier_name' => $classifierName], 'lookup_status' => 'found', 'is_active' => true,
        ]);
    }

    private static function apiError(string $class, int $status, string $type, string $message): \Throwable
    {
        return new $class(new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response($status, ['Content-Type' => 'application/json'], json_encode(['type' => 'error', 'error' => ['type' => $type, 'message' => $message]])));
    }

    public function test_product_name_by_haiku_in_latin_uzbek_and_russian(): void
    {
        $this->claude->queue[] = ['name_uz' => 'Beltop BT-EK-5510 elektr choynak, oq', 'name_ru' => 'Электрочайник Beltop BT-EK-5510, белый'];
        $p = $this->product('Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг');

        $this->assertSame('ai', app(ProductNamer::class)->name($p));
        $p->refresh();
        $this->assertSame('Beltop BT-EK-5510 elektr choynak, oq', $p->name_uz);
        $this->assertSame('Электрочайник Beltop BT-EK-5510, белый', $p->name_ru);

        $this->assertSame('claude-haiku-5-5', $this->claude->sent[0]['model'], 'cheap model for this task');
        $this->assertArrayNotHasKey('fallbacks', $this->claude->sent[0], 'Haiku has no server-side fallback');
        $usage = AiUsage::firstOrFail();
        $this->assertSame('ok', $usage->status);
        $this->assertEqualsWithDelta((1000 * 0.10 + 200 * 0.50) / 1e6, $usage->cost_usd, 1e-7);
    }

    public function test_without_key_names_are_transliterated(): void
    {
        config(['ai.api_key' => null]);
        $p = $this->product('Сув қайнатиш учун электр чойнак: Beltop, BT-EK-5510 оқ ранг');

        $this->assertSame('fallback', app(ProductNamer::class)->name($p));
        $this->assertSame('Beltop, BT-EK-5510 oq rang', $p->fresh()->name_uz);
        $this->assertSame([], $this->claude->sent, 'no API call');
    }

    public function test_credit_finished_pauses_ai_alerts_admin_once_and_falls_back(): void
    {
        config(['telegram.token' => 'TEST', 'telegram.admin_chat' => '777']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
        $this->claude->queue[] = self::apiError(BadRequestException::class, 400, 'invalid_request_error', 'Your credit balance is too low to access the Anthropic API.');

        $a = $this->product('Чойнак: Beltop A оқ');
        $b = $this->product('Чойнак: Beltop B қора');
        app(ProductNamer::class)->name($a);
        app(ProductNamer::class)->name($b);

        $this->assertSame('Beltop A oq', $a->fresh()->name_uz, 'fallback after the failed call');
        $this->assertSame('Beltop B qora', $b->fresh()->name_uz);
        $this->assertCount(1, $this->claude->sent, 'paused: the second product did not call the API');
        $this->assertSame('credit', $this->claude->unavailableReason());
        Http::assertSentCount(1); // one Telegram alert
        Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] == '777');

        $this->claude->resume();
        $this->assertTrue($this->claude->available());
    }

    public function test_error_kinds(): void
    {
        $this->assertSame('credit', Claude::classify(self::apiError(PaymentRequiredException::class, 402, 'billing_error', 'Payment required')));
        $this->assertSame('retry', Claude::classify(self::apiError(RateLimitException::class, 429, 'rate_limit_error', 'Too many')));
        $this->assertSame('error', Claude::classify(self::apiError(BadRequestException::class, 400, 'invalid_request_error', 'max_tokens too large')));
    }

    public function test_monthly_limit_stops_calls(): void
    {
        config(['ai.monthly_limit_usd' => 0.001]);
        AiUsage::create(['feature' => 'product_name', 'model' => 'claude-haiku-5-5', 'cost_usd' => 0.002, 'status' => 'ok']);
        $this->assertFalse($this->claude->available());
        $this->assertSame('limit', $this->claude->unavailableReason());
    }

    public function test_a_name_typed_by_a_person_is_never_replaced(): void
    {
        $p = $this->product('Чойнак: Beltop C', 'Mening choynagim');
        $this->assertSame('skipped', app(ProductNamer::class)->name($p));
        $this->assertSame('Mening choynagim', $p->fresh()->name_uz);
    }

    public function test_marketplace_item_texts_and_faq_in_three_languages(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $category = OfferCategory::create(['slug' => 'printers-ai', 'name_uz' => 'Printerlar', 'name_ru' => 'Принтеры', 'icon' => 'heroicon-o-printer', 'is_active' => true, 'sort_order' => 99]);
        $faq = ['q_uz' => 'Qaysi etiketkalar mos?', 'a_uz' => '58 mm gacha.', 'q_ru' => 'Какие этикетки подходят?', 'a_ru' => 'До 58 мм.', 'q_zh' => '适合哪些标签？', 'a_zh' => '最宽 58 毫米。'];
        $this->claude->queue[] = [
            'title_uz' => 'AI nomi', 'title_ru' => 'Принтер Xprinter XP-365B', 'title_zh' => 'Xprinter XP-365B 打印机',
            'description_uz' => "Termo etiketka printeri.", 'description_ru' => 'Термопринтер этикеток.', 'description_zh' => '热敏标签打印机。',
            'faq' => [$faq],
        ];

        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\OfferForm::class, ['category' => $category])
            ->set('item.title_uz', 'Xprinter XP-365B')->set('aiNotes', '58 mm, USB')
            ->call('generateContent')
            ->assertSet('fillConflicts', [__('common.name') . ' · UZ'])   // another name suggested: the form asks first
            ->call('applyFill', false)                                     // "fill empty only"
            ->assertSet('item.title_uz', 'Xprinter XP-365B')            // typed name kept
            ->assertSet('item.title_ru', 'Принтер Xprinter XP-365B')
            ->assertSet('item.description_zh', '热敏标签打印机。')
            ->call('save')->assertHasNoErrors();

        $offer = Offer::where('title_uz', 'Xprinter XP-365B')->firstOrFail();
        $this->assertSame('Qaysi etiketkalar mos?', $offer->faq[0]['q_uz']);
        $this->assertStringContainsString('58 mm, USB', $this->claude->sent[0]['messages'][0]['content'], 'admin notes are given to Claude');
        app()->setLocale('ru');
        $this->assertSame('Какие этикетки подходят?', $offer->localizedFaq()[0]['q']);
    }

    public function test_marketplace_button_without_ai_explains_and_keeps_the_form(): void
    {
        config(['ai.api_key' => null]);
        $admin = User::create(['name' => 'A', 'email' => 'a2@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $category = OfferCategory::create(['slug' => 'ribbons-ai', 'name_uz' => 'Ribbonlar', 'icon' => 'heroicon-o-film', 'is_active' => true, 'sort_order' => 98]);

        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\OfferForm::class, ['category' => $category])
            ->set('item.title_uz', 'Wax ribbon')
            ->call('generateContent')
            ->assertSet('aiMessage', __('ai.unavailable.no_key'))
            ->assertSet('item.title_uz', 'Wax ribbon');
        $this->assertSame([], $this->claude->sent);
    }

    public function test_new_model_version_is_picked_up_without_code_changes(): void
    {
        $this->assertSame('claude-haiku-5-5', $this->resolver->forTask('product_name'));

        // "Claude Haiku 6" appears in the Models API
        $this->resolver->models[] = ['id' => 'claude-haiku-6', 'line' => 'haiku', 'created' => 2000];
        $this->assertSame('claude-haiku-5-5', $this->resolver->forTask('product_name'), 'cached for a day');
        $this->resolver->refresh();
        $this->assertSame('claude-haiku-6', $this->resolver->forTask('product_name'));

        $this->claude->queue[] = ['name_uz' => 'X', 'name_ru' => 'Y'];
        app(ProductNamer::class)->name($this->product('Чойнак: Beltop D'));
        $this->assertSame('claude-haiku-6', $this->claude->sent[0]['model']);
        $this->assertSame('claude-haiku-6', AiUsage::latest('id')->first()->model);
    }

    public function test_level_is_chosen_per_task_in_settings(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'a3@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]);
        $this->actingAs($admin);
        Livewire::test(\App\Http\Livewire\Admin\AiSettings::class)->set('levels.offer_content', 'sonnet')->assertSee('claude-sonnet-5-5');

        $this->assertSame('claude-sonnet-5-5', $this->resolver->forTask('offer_content'));
        $this->assertSame('claude-haiku-5-5', $this->resolver->forTask('product_name'), 'other task unchanged');

        $this->claude->queue[] = ['title_uz' => 'a', 'title_ru' => 'b', 'title_zh' => 'c', 'description_uz' => 'd', 'description_ru' => 'e', 'description_zh' => 'f', 'faq' => []];
        app(\App\Domain\Ai\OfferWriter::class)->write(['title' => 'T', 'category' => 'C', 'price' => null, 'url' => null, 'notes' => null]);
        $this->assertSame('default', $this->claude->sent[0]['fallbacks'], 'Sonnet gets the refusal fallback, Haiku does not');
    }

    public function test_models_api_down_uses_the_last_known_model(): void
    {
        $resolver = new class extends \App\Domain\Ai\ModelResolver {
            protected function activeModels(): iterable { throw new \RuntimeException('network'); }
        };
        $this->assertSame('claude-haiku-5-5', $resolver->newest('haiku'));
    }
}
