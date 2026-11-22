<?php

namespace Tests\Feature;

use App\Domain\Ai\OfferWriter;
use App\Domain\Content\Banner;
use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Settings\Setting;
use App\Http\Livewire\Admin\BannerForm;
use App\Http\Livewire\Admin\BannerIndex;
use App\Http\Livewire\Admin\DriverForm;
use App\Http\Livewire\Admin\DriverIndex;
use App\Http\Livewire\Admin\OfferCategoryManager;
use App\Http\Livewire\Admin\OfferForm;
use App\Http\Livewire\Admin\SeoPage;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Stage 2 of the admin redesign: products, categories, drivers, banners, SEO and the templates list on the shared multilingual form. */
class AdminContentFormsTest extends TestCase
{
    use RefreshDatabase;

    private OfferCategory $printers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->printers = OfferCategory::create(['slug' => 'printers', 'icon' => 'heroicon-o-printer', 'name_uz' => 'Printerlar', 'name_ru' => 'Принтеры', 'name_zh' => '打印机',
            'description_uz' => 'Tavsif', 'description_ru' => 'Описание', 'description_zh' => '说明', 'is_active' => true, 'sort_order' => 1]);
        $this->actingAs(User::create(['name' => 'A', 'email' => 'a@t.uz', 'password' => 'secret123', 'role' => 'admin', 'locale' => 'uz', 'is_active' => true]));
    }

    private function offer(array $values = []): Offer
    {
        static $n = 0;
        $n++;

        return $this->printers->offers()->create($values + ['slug' => "item-$n", 'category' => 'printers', 'title_uz' => "Printer $n", 'action' => 'order', 'sort_order' => $n, 'is_active' => true]);
    }

    private function fullOffer(array $values = []): Offer
    {
        return $this->offer($values + ['title_ru' => 'Принтер', 'title_zh' => '打印机', 'description_uz' => 'Tavsif', 'description_ru' => 'Описание', 'description_zh' => '说明']);
    }

    private function faqRow(array $values = []): array
    {
        return $values + ['q_uz' => '', 'a_uz' => '', 'q_ru' => '', 'a_ru' => '', 'q_zh' => '', 'a_zh' => ''];
    }

    /** AI answers without a real call. */
    private function fakeWriter(array $result): void
    {
        config(['ai.api_key' => 'sk-test', 'ai.enabled' => true]);
        $this->app->instance(OfferWriter::class, new class($result) extends OfferWriter {
            public int $calls = 0;

            public function __construct(private array $result) {}

            public function write(array $input): ?array
            {
                $this->calls++;

                return $this->result;
            }
        });
    }

    private function nav(string $html): string
    {
        $nav = substr($html, strpos($html, 'data-admin-nav'));

        return substr($nav, 0, strpos($nav, '</nav>'));
    }

    // ---------- marketplace product ----------

    public function test_saving_a_product_stays_on_the_page_and_a_new_one_continues_on_its_edit_address(): void
    {
        $offer = $this->offer();

        Livewire::test(OfferForm::class, ['category' => $this->printers, 'offer' => $offer])
            ->set('item.title_ru', 'Принтер этикеток')
            ->call('save')
            ->assertHasNoErrors()->assertNoRedirect()
            ->assertDispatched('saved')
            ->assertDispatched('notify', type: 'success', message: __('common.saved'));
        $this->assertSame('Принтер этикеток', $offer->fresh()->title_ru);
        $this->assertSame($offer->slug, $offer->fresh()->slug);

        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Yangi printer')->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.offers.edit', [$this->printers, Offer::where('slug', 'yangi-printer')->firstOrFail()]));
    }

    public function test_product_error_in_a_hidden_language_is_reported_on_its_key_and_keeps_the_text(): void
    {
        $offer = $this->offer();
        $long = str_repeat('я', 401);

        Livewire::test(OfferForm::class, ['category' => $this->printers, 'offer' => $offer])
            ->set('item.description_ru', $long)
            ->call('save')
            ->assertHasErrors(['item.description_ru'])
            ->assertHasNoErrors(['item.title_uz', 'item.description_uz'])
            ->assertSet('item.description_ru', $long);   // nothing is cut or lost
        $this->assertNull($offer->fresh()->description_ru);
    }

    public function test_faq_row_without_uzbek_question_and_answer_blocks_the_save(): void
    {
        $offer = $this->offer(['faq' => [$this->faqRow(['q_uz' => 'Savol?', 'a_uz' => 'Javob.'])]]);

        Livewire::test(OfferForm::class, ['category' => $this->printers, 'offer' => $offer])
            ->call('addFaq')
            ->set('item.faq.1.q_ru', 'Вопрос только по-русски?')
            ->set('item.faq.1.a_ru', 'Ответ.')
            ->call('save')
            ->assertHasErrors(['item.faq.1'])
            ->assertHasNoErrors(['item.faq.0'])
            ->assertSee(__('admin.i18n.row_uz_required'))
            ->assertSet('item.faq.1.q_ru', 'Вопрос только по-русски?')      // the row is kept, not dropped
            // a question without its Uzbek answer is blocked as well
            ->set('item.faq.1.q_uz', 'Faqat savol?')
            ->call('save')->assertHasErrors(['item.faq.1'])
            ->set('item.faq.1.a_uz', 'Javob ham bor.')
            ->call('save')->assertHasNoErrors();

        $faq = $offer->fresh()->faq;
        $this->assertCount(2, $faq);
        $this->assertSame(['q_uz', 'a_uz', 'q_ru', 'a_ru', 'q_zh', 'a_zh'], array_keys($faq[1]));   // the structure the MCP tools read
        $this->assertSame('Вопрос только по-русски?', $faq[1]['q_ru']);

        // an untouched empty row is not dropped silently either
        Livewire::test(OfferForm::class, ['category' => $this->printers, 'offer' => $offer->fresh()])
            ->call('addFaq')->call('save')->assertHasErrors(['item.faq.2'])
            ->call('removeFaq', 2)->call('save')->assertHasNoErrors();
        $this->assertCount(2, $offer->fresh()->faq);
    }

    public function test_ai_fill_fills_only_empty_fields_and_asks_before_overwriting(): void
    {
        $ai = ['title_uz' => 'AI nomi', 'title_ru' => 'AI название', 'title_zh' => 'AI 名称',
            'description_uz' => 'AI tavsif', 'description_ru' => 'AI описание', 'description_zh' => 'AI 说明',
            'faq' => [$this->faqRow(['q_uz' => 'AI savol?', 'a_uz' => 'AI javob.', 'q_ru' => 'AI вопрос?', 'a_ru' => 'AI ответ.'])]];
        $this->fakeWriter($ai);

        // no Uzbek name: the AI does not run (the button is disabled in the browser, the server checks too)
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_ru', 'Только русское')
            ->call('generateContent')
            ->assertSet('aiMessage', __('ai.title_first'))->assertSet('pendingFill', null)->assertSet('item.description_uz', '');

        // only the name typed and the AI keeps it: everything empty is filled straight away, no question
        $this->fakeWriter(['title_uz' => 'Mening printerim'] + $ai);
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')
            ->call('generateContent')
            ->assertSet('fillConflicts', [])->assertSet('pendingFill', null)
            ->assertSet('item.title_uz', 'Mening printerim')
            ->assertSet('item.title_ru', 'AI название')
            ->assertSet('item.description_uz', 'AI tavsif')
            ->assertSet('item.faq.0.q_uz', 'AI savol?')
            ->assertDispatched('form-changed');

        // the AI suggests another name: the form asks; "overwrite" puts the new name in, "empty only" keeps it
        $this->fakeWriter($ai);
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')
            ->call('generateContent')
            ->assertSet('fillConflicts', [__('common.name') . ' · UZ'])
            ->assertSet('item.title_uz', 'Mening printerim')
            ->call('applyFill', true)
            ->assertSet('item.title_uz', 'AI nomi')->assertSet('item.slug', 'ai-nomi');   // new product: the address follows the name
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')
            ->call('generateContent')->call('applyFill', false)
            ->assertSet('item.title_uz', 'Mening printerim')->assertSet('item.title_ru', 'AI название');

        // a description and a FAQ are already written: the form asks first and changes nothing
        $typed = [$this->faqRow(['q_uz' => 'Mening savolim?', 'a_uz' => 'Mening javobim.'])];
        $form = Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')
            ->set('item.description_uz', 'Mening tavsifim')
            ->set('item.faq', $typed)
            ->call('generateContent')
            ->assertSet('fillConflicts', [__('common.name') . ' · UZ', __('offers.description') . ' · UZ', __('ai.faq') . ' · 1'])
            ->assertSet('item.description_uz', 'Mening tavsifim')
            ->assertSet('item.description_ru', '')
            ->assertSet('item.faq', $typed)
            ->assertSee(__('admin.fill.ai_title'))->assertSee(__('admin.fill.empty_only'))->assertSee(__('admin.fill.overwrite'));

        // "fill empty only": the typed texts stay, the empty ones are filled
        (clone $form)->call('applyFill', false)
            ->assertSet('item.description_uz', 'Mening tavsifim')
            ->assertSet('item.description_ru', 'AI описание')
            ->assertSet('item.faq', $typed)
            ->assertSet('fillConflicts', [])->assertDontSee(__('admin.fill.ai_title'));

        // "cancel": nothing changes
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')->set('item.description_uz', 'Mening tavsifim')
            ->call('generateContent')->call('cancelFill')
            ->assertSet('item.description_uz', 'Mening tavsifim')->assertSet('item.description_ru', '')->assertSet('pendingFill', null);

        // "overwrite": names, descriptions and the FAQ are replaced
        Livewire::test(OfferForm::class, ['category' => $this->printers])
            ->set('item.title_uz', 'Mening printerim')->set('item.description_uz', 'Mening tavsifim')->set('item.faq', $typed)
            ->call('generateContent')->call('applyFill', true)
            ->assertSet('item.title_uz', 'AI nomi')
            ->assertSet('item.description_uz', 'AI tavsif')
            ->assertSet('item.faq.0.q_uz', 'AI savol?');
    }

    public function test_product_form_has_one_language_switch_counters_from_the_rules_and_no_price(): void
    {
        $offer = $this->offer(['faq' => [$this->faqRow(['q_uz' => 'Savol?', 'a_uz' => 'Javob.'])]]);
        $html = $this->get(route('admin.offers.edit', [$this->printers, $offer]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'role="tablist"'));
        $this->assertSame(1, substr_count($html, 'data-save-bar'));
        foreach (['item.title', 'item.description', 'item.faq.0.q', 'item.faq.0.a'] as $field) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $this->assertStringContainsString("=\"{$field}_$l\"", $html);
            }
        }
        foreach ([160, 400, 200, 600] as $max) {
            $this->assertStringContainsString("data-max=\"$max\"", $html);
        }
        $this->assertDoesNotMatchRegularExpression('/data-i18n="[a-z]+"[^>]*maxlength|maxlength[^>]*data-i18n=/', $html);
        $this->assertStringContainsString('id="row-item-faq-0"', $html);
        $this->assertStringNotContainsString('wire:click="$set(\'locale\'', $html);
        $form = substr($html, strpos($html, '<form'), strpos($html, '</form>') - strpos($html, '<form'));
        $this->assertStringNotContainsStringIgnoringCase('price', $form);
        $this->assertStringNotContainsString(__('offers.price'), $form);
    }

    // ---------- category page ----------

    public function test_category_page_lists_products_with_status_and_languages_and_filters_incomplete(): void
    {
        $this->fullOffer(['title_uz' => 'Toliq printer']);
        $this->offer(['title_uz' => 'Tarjimasiz printer']);
        $this->fullOffer(['title_uz' => 'FAQ tarjimasiz', 'faq' => [$this->faqRow(['q_uz' => 'S?', 'a_uz' => 'J.', 'q_ru' => 'В?', 'a_ru' => 'О.'])]]);   // zh FAQ missing
        $hidden = $this->fullOffer(['title_uz' => 'Yopiq printer', 'is_active' => false]);

        Livewire::test(OfferCategoryManager::class, ['category' => $this->printers])
            ->assertSee(['Toliq printer', 'Tarjimasiz printer', 'FAQ tarjimasiz', 'Yopiq printer'])
            ->assertSee([__('admin.status.published'), __('admin.status.hidden'), __('admin.list.missing'), __('admin.list.move_up')])
            ->assertViewHas('missingCount', 2)
            ->assertDontSee(__('offers.category_settings_hint'))        // the settings form is not above the list any more
            ->set('missing', true)
            ->assertDontSee('Toliq printer')->assertSee(['Tarjimasiz printer', 'FAQ tarjimasiz'])
            ->assertDontSee(__('admin.list.move_up'))->assertSee(__('admin.list.move_filtered'))   // order is changed in the whole list only
            ->set('missing', false)->set('status', 'hidden')
            ->assertSee('Yopiq printer')->assertDontSee('Toliq printer')
            ->call('clearFilters')
            ->call('toggle', $hidden->id)
            ->call('delete', $hidden->id)
            ->assertDontSee('Yopiq printer');

        $this->assertNull(Offer::find($hidden->id));
        $this->get(route('admin.offers', [$this->printers, 'missing' => 1]))->assertOk()->assertSee('Tarjimasiz printer')->assertDontSee('Toliq printer');
    }

    public function test_category_settings_are_a_tab_with_the_multilingual_form_and_keep_the_icon(): void
    {
        $html = $this->get(route('admin.offers', [$this->printers, 'tab' => 'settings']))->assertOk()
            ->assertSee(__('offers.category_settings_hint'))->assertSee(__('offers.market_url'))->getContent();
        $this->assertSame(1, substr_count($html, 'data-lang-tab="uz"'));
        foreach (['name', 'description'] as $field) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $this->assertStringContainsString("wire:model=\"settings.{$field}_$l\"", $html);
            }
        }
        $this->assertStringContainsString('data-max="120"', $html);
        $this->assertStringContainsString('data-max="255"', $html);
        $this->assertStringNotContainsString('settings.icon', $html);

        Livewire::test(OfferCategoryManager::class, ['category' => $this->printers])
            ->set('tab', 'settings')
            ->set('settings.name_ru', str_repeat('я', 121))
            ->call('saveSettings')->assertHasErrors(['settings.name_ru'])->assertHasNoErrors(['settings.name_uz'])
            ->set('settings.name_ru', 'Принтеры этикеток')->set('settings.market_url', 'https://uzum.uz/uz/shop/markor')->set('settings.is_active', false)
            ->call('saveSettings')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('saved');

        $category = $this->printers->fresh();
        $this->assertSame('Принтеры этикеток', $category->name_ru);
        $this->assertSame('https://uzum.uz/uz/shop/markor', $category->market_url);
        $this->assertFalse($category->is_active);
        $this->assertSame('heroicon-o-printer', $category->icon);   // not edited here, the stored value stays
    }

    // ---------- printer drivers ----------

    public function test_driver_form_has_translations_as_fields_stays_on_the_page_and_reports_a_hidden_language(): void
    {
        $driver = PrinterDriver::create(['brand' => 'Zebra', 'model' => 'ZD421', 'slug' => 'zebra-zd421', 'kind' => 'desktop', 'os' => ['windows'],
            'driver_url' => 'https://www.zebra.com/zd421', 'summary_uz' => 'Qisqa', 'is_active' => true]);

        $html = $this->get(route('admin.drivers.edit', $driver))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'role="tablist"'));
        foreach (['summary' => 300, 'notes' => 3000] as $field => $max) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $this->assertStringContainsString("id=\"f-item-$field-$l\"", $html);
            }
            $this->assertStringContainsString("data-max=\"$max\"", $html);
        }
        $this->assertStringNotContainsString('<details', $html);   // translations are not inside a folded block
        $this->assertDoesNotMatchRegularExpression('/data-i18n="[a-z]+"[^>]*maxlength|maxlength[^>]*data-i18n=/', $html);

        $long = str_repeat('я', 301);
        Livewire::test(DriverForm::class, ['driver' => $driver])
            ->set('item.summary_ru', $long)
            ->call('save')->assertHasErrors(['item.summary_ru'])->assertSet('item.summary_ru', $long)
            ->set('item.summary_ru', 'Кратко')->set('item.notes_zh', '备注')
            ->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('saved');
        $this->assertSame('Кратко', $driver->fresh()->summary_ru);
        $this->assertSame('备注', $driver->fresh()->notes_zh);
        $this->assertSame('zebra-zd421', $driver->fresh()->slug);

        // a changed name moves the page: the form continues on the new address
        Livewire::test(DriverForm::class, ['driver' => $driver->fresh()])
            ->set('item.model', 'ZD621')->call('save')->assertRedirect(route('admin.drivers.edit', 'zebra-zd621'));

        Livewire::test(DriverIndex::class)
            ->assertSee('Zebra ZD621')->assertSee(__('admin.status.published'))->assertViewHas('missingCount', 1)
            ->set('missing', true)->assertSee('Zebra ZD621')
            ->set('status', 'hidden')->assertDontSee('Zebra ZD621')->assertSee(__('admin.list.none_found'));
    }

    // ---------- banners ----------

    public function test_banner_save_stays_on_the_page_and_the_form_has_one_language_switch(): void
    {
        $banner = Banner::create(['title_uz' => 'Aksiya', 'style' => 'light', 'is_active' => true, 'sort_order' => 1]);

        $html = $this->get(route('admin.banners.edit', $banner))->assertOk()->assertSee(__('banners.preview'))->getContent();
        $this->assertSame(1, substr_count($html, 'role="tablist"'));            // no second switch in the preview
        $this->assertStringNotContainsString('wire:click="$set(\'locale\'', $html);
        foreach (Banner::LIMITS as $field => $max) {
            $this->assertStringContainsString("id=\"f-item-$field-zh\"", $html);
            $this->assertStringContainsString("data-max=\"$max\"", $html);
        }

        $long = str_repeat('я', 161);
        Livewire::test(BannerForm::class, ['banner' => $banner])
            ->set('item.title_ru', $long)->call('save')->assertHasErrors(['item.title_ru'])->assertSet('item.title_ru', $long)
            ->set('item.title_ru', 'Акция')->set('locale', 'ru')
            ->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('saved');
        $this->assertSame('Акция', $banner->fresh()->title_ru);

        Livewire::test(BannerForm::class)->set('item.title_uz', 'Yangi banner')->call('save')
            ->assertRedirect(route('admin.banners.edit', Banner::where('title_uz', 'Yangi banner')->firstOrFail()));
    }

    public function test_choosing_a_product_fills_only_empty_banner_fields_and_asks_before_overwriting(): void
    {
        $offer = $this->fullOffer(['slug' => 'zebra-zd421', 'title_uz' => 'Zebra ZD421', 'title_ru' => 'Зебра ZD421', 'description_uz' => 'Birinchi gap. Ikkinchi gap.']);

        // empty form: filled straight away
        Livewire::test(BannerForm::class)
            ->set('item.offer_id', $offer->id)
            ->assertSet('fillConflicts', [])
            ->assertSet('item.title_uz', 'Zebra ZD421')->assertSet('item.text_uz', 'Birinchi gap.')->assertSet('item.url', '/marketplace/zebra-zd421');

        // typed texts: nothing is replaced, the dialog lists what would be
        $form = Livewire::test(BannerForm::class)
            ->set('item.title_uz', 'Mening sarlavham')->set('item.text_ru', 'Мой текст')->set('item.url', '/label-design')
            ->set('item.offer_id', $offer->id)
            ->assertSet('item.offer_id', $offer->id)
            ->assertSet('fillConflicts', [__('banners.link'), __('banners.title') . ' · UZ', __('banners.text') . ' · RU'])
            ->assertSet('item.title_uz', 'Mening sarlavham')->assertSet('item.title_ru', '')
            ->assertSee(__('admin.fill.product_title'))->assertSee(__('admin.fill.empty_only'));

        (clone $form)->call('applyFill', false)
            ->assertSet('item.title_uz', 'Mening sarlavham')->assertSet('item.text_ru', 'Мой текст')->assertSet('item.url', '/label-design')
            ->assertSet('item.title_ru', 'Зебра ZD421')->assertSet('item.text_uz', 'Birinchi gap.')
            ->assertSet('fillConflicts', [])->assertDontSee(__('admin.fill.product_title'));

        Livewire::test(BannerForm::class)
            ->set('item.title_uz', 'Mening sarlavham')->set('item.offer_id', $offer->id)
            ->call('cancelFill')
            ->assertSet('item.title_uz', 'Mening sarlavham')->assertSet('item.title_ru', '')->assertSet('item.offer_id', $offer->id);

        Livewire::test(BannerForm::class)
            ->set('item.title_uz', 'Mening sarlavham')->set('item.text_ru', 'Мой текст')->set('item.offer_id', $offer->id)
            ->call('applyFill', true)
            ->assertSet('item.title_uz', 'Zebra ZD421')->assertSet('item.text_ru', 'Описание')->assertSet('item.url', '/marketplace/zebra-zd421');
    }

    public function test_banner_list_has_status_languages_and_filters(): void
    {
        Banner::create(['is_default' => true, 'title_uz' => 'Standart', 'style' => 'light', 'is_active' => true]);
        Banner::create(['title_uz' => 'Toliq banner', 'title_ru' => 'Б', 'title_zh' => '横', 'text_uz' => 'm', 'text_ru' => 'т', 'text_zh' => '文', 'style' => 'dark', 'is_active' => true, 'sort_order' => 1]);
        Banner::create(['title_uz' => 'Kelajak banner', 'style' => 'light', 'is_active' => true, 'starts_at' => now()->addWeek()->toDateString(), 'sort_order' => 2]);
        Banner::create(['title_uz' => 'Tugagan banner', 'style' => 'light', 'is_active' => true, 'ends_at' => now()->subWeek()->toDateString(), 'sort_order' => 3]);

        Livewire::test(BannerIndex::class)
            ->assertSee(['Toliq banner', 'Kelajak banner', 'Tugagan banner', __('banners.default')])
            ->assertSee([__('admin.status.published'), __('admin.status.scheduled'), __('admin.status.hidden')])
            ->assertViewHas('missingCount', 2)        // the built-in banner and the button text do not count
            ->set('missing', true)->assertDontSee('Toliq banner')->assertSee('Kelajak banner')->assertDontSee(__('banners.default_hint'))
            ->set('missing', false)->set('status', 'scheduled')->assertSee('Kelajak banner')->assertDontSee('Tugagan banner')
            ->set('status', 'hidden')->assertSee('Tugagan banner')->assertDontSee('Kelajak banner');
    }

    // ---------- menu ----------

    public function test_menu_shows_translation_badges_for_marketplace_categories_banners_and_drivers(): void
    {
        $scanners = OfferCategory::create(['slug' => 'scanners', 'name_uz' => 'Skanerlar', 'is_active' => true, 'sort_order' => 2]);
        $this->fullOffer();
        $this->offer();
        $this->offer();
        $scanners->offers()->create(['slug' => 'scanner', 'category' => 'scanners', 'title_uz' => 'Skaner', 'action' => 'order', 'is_active' => true]);
        Banner::create(['is_default' => true, 'title_uz' => 'Standart', 'style' => 'light', 'is_active' => true]);
        Banner::create(['title_uz' => 'Aksiya', 'style' => 'light', 'is_active' => true]);
        PrinterDriver::create(['brand' => 'Zebra', 'model' => 'ZD421', 'slug' => 'zebra-zd421', 'kind' => 'desktop', 'driver_url' => 'https://www.zebra.com/zd421', 'is_active' => true]);
        PrinterDriver::create(['brand' => 'Godex', 'model' => 'G500', 'slug' => 'godex-g500', 'kind' => 'desktop', 'driver_url' => 'https://www.godexintl.com/g500', 'is_active' => true,
            'summary_uz' => 'a', 'summary_ru' => 'б', 'summary_zh' => '文', 'notes_uz' => 'a', 'notes_ru' => 'б', 'notes_zh' => '文']);

        $nav = $this->nav($this->get(route('admin.stats'))->assertOk()->getContent());
        $badge = fn (string $path, int $n) => '/href="[^"]*' . preg_quote($path, '/') . '\?missing=1"[^>]*data-nav-gap[^>]*>.*?<\/svg>\s*' . $n . '\s*<\/a>/s';

        $this->assertMatchesRegularExpression($badge('admin/offers/printers', 2), $nav);
        $this->assertMatchesRegularExpression($badge('admin/offers/scanners', 1), $nav);
        $this->assertMatchesRegularExpression($badge('admin/banners', 1), $nav);     // the built-in banner is not counted
        $this->assertMatchesRegularExpression($badge('admin/drivers', 1), $nav);
        // the Marketplace row sums its categories
        $this->assertMatchesRegularExpression('/' . preg_quote(__('nav.offers'), '/') . '<\/span>\s*<span[^>]*title="' . preg_quote(e(__('admin.nav.gap', ['n' => 3])), '/') . '"/s', $nav);
        $this->assertStringNotContainsString('TODO', $nav);

        // everything translated: the badges disappear
        Offer::query()->update(['title_ru' => 'т', 'title_zh' => '题', 'description_uz' => 'd', 'description_ru' => 'о', 'description_zh' => '说']);
        Banner::query()->update(['title_ru' => 'т', 'title_zh' => '题', 'text_uz' => 'm', 'text_ru' => 'т', 'text_zh' => '文']);
        PrinterDriver::query()->update(['summary_uz' => 'a', 'summary_ru' => 'б', 'summary_zh' => '文', 'notes_uz' => 'a', 'notes_ru' => 'б', 'notes_zh' => '文']);
        $nav = $this->nav($this->get(route('admin.stats'))->getContent());
        $this->assertStringNotContainsString('data-nav-gap', $nav);
    }

    // ---------- SEO ----------

    public function test_seo_page_reports_too_long_texts_per_language_and_keeps_them(): void
    {
        $title = str_repeat('я', Seo::MAX['title'] + 1);
        $description = str_repeat('字', Seo::MAX['description'] + 1);

        Livewire::test(SeoPage::class)
            ->set('texts.home.title.ru', $title)
            ->set('texts.faq.description.zh', $description)
            ->set('texts.studio.title.uz', 'Etiketka dizayni — Markor')
            ->call('save')
            ->assertHasErrors(['texts.home.title.ru', 'texts.faq.description.zh'])
            ->assertHasNoErrors(['texts.studio.title.uz', 'texts.home.title.uz'])
            ->assertSet('texts.home.title.ru', $title);                   // nothing is cut
        $this->assertNull(Setting::all_()['seo.studio.title.uz'] ?? null, 'nothing is saved while there are errors');

        // longer than recommended but within the limit: saved (the counter only warns)
        Livewire::test(SeoPage::class)
            ->set('texts.home.title.ru', str_repeat('я', Seo::FIELDS['title'] + 20))
            ->call('save')->assertHasNoErrors()->assertNoRedirect()->assertDispatched('saved');
        $this->assertSame(str_repeat('я', 80), Seo::get('home.title.ru'));
    }

    public function test_seo_page_uses_the_single_language_switch_with_counters_and_keeps_the_other_sections(): void
    {
        $html = $this->get(route('admin.seo'))->assertOk()
            ->assertSee([__('seo_admin.engines'), __('seo_admin.indexing'), __('seo_admin.noindex'), __('seo_admin.robots_extra'), __('seo_admin.ai_agents')])
            ->assertSee(['Google Search Console', 'Yandex Metrika', 'llms.txt'])->getContent();

        $this->assertSame(1, substr_count($html, 'data-lang-tab="ru"'));
        $this->assertStringNotContainsString('wire:click="$set(\'locale\'', $html);
        foreach (Seo::PAGES as $page) {
            foreach (['uz', 'ru', 'zh'] as $l) {
                $this->assertStringContainsString("wire:model=\"texts.$page.title.$l\"", $html);
                $this->assertStringContainsString("wire:model=\"texts.$page.description.$l\"", $html);
            }
        }
        // recommended length per language (zh is shorter), the hard limit only as a counter
        $this->assertMatchesRegularExpression('/id="f-texts-home-title-uz"[^>]*data-max="120"[^>]*data-soft="60"/', $html);
        $this->assertMatchesRegularExpression('/id="f-texts-home-title-zh"[^>]*data-max="120"[^>]*data-soft="32"/', $html);
        $this->assertMatchesRegularExpression('/id="f-texts-home-description-zh"[^>]*data-max="320"[^>]*data-soft="85"/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-i18n="[a-z]+"[^>]*maxlength|maxlength[^>]*data-i18n=/', $html);
        // the default text is the placeholder and counts as the shown text
        $this->assertStringContainsString('data-default="' . e(__('seo.home.title', [], 'ru')) . '"', $html);
        foreach (['codes.google', 'codes.metrika', 'noindex', 'robotsExtra', 'aiAgents'] as $model) {
            $this->assertStringContainsString("wire:model=\"$model\"", $html);
        }
        $this->assertSame(1, substr_count($html, 'data-save-bar'));
    }

    // ---------- label templates list ----------

    public function test_label_templates_list_uses_the_list_components(): void
    {
        $template = \App\Domain\Printing\Models\LabelTemplate::default();

        $html = $this->get(route('label-templates.index'))->assertOk()
            ->assertSee($template->name)->assertSee(__('templates.new_from_default'))->assertSee(__('templates.admin_hint'))->getContent();
        $this->assertStringContainsString('admin-list-grid', $html);
        $this->assertStringNotContainsString('<table', $html);
        $this->assertStringNotContainsString('wire:confirm', $html);
        $this->assertStringContainsString("wire:click=\"openEdit({$template->id})\"", $html);
    }
}
