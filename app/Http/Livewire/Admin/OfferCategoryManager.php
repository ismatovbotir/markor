<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use App\Support\AdminI18n;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Super admin: one marketplace category. Tab "items" — the products list (order, show / hide, delete);
 * tab "settings" — names and descriptions in uz / ru / zh, market link, visibility. A product is edited on its own page (OfferForm).
 */
#[Layout('layouts.app')]
class OfferCategoryManager extends Component
{
    public const TABS = ['items', 'settings'];
    public const STATUSES = ['all', 'published', 'hidden'];

    public OfferCategory $category;

    #[Url(except: 'items')]
    public string $tab = 'items';

    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    /** Only products with an empty ru / zh text (the menu badge opens the list with ?missing=1). */
    #[Url(except: false)]
    public bool $missing = false;

    /** category settings */
    public array $settings = [];

    public function mount(OfferCategory $category): void
    {
        $this->category = $category;
        $this->settings = collect($category->only(['name_uz', 'name_ru', 'name_zh', 'description_uz', 'description_ru', 'description_zh', 'market_url']))
            ->map(fn ($v) => $v ?? '')->all() + ['is_active' => (bool) $category->is_active];
    }

    /** Limits of the settings texts: one source for the counters and the validation. */
    public static function settingsRules(): array
    {
        return [
            'settings.name_uz'        => 'required|string|max:120',
            'settings.name_ru'        => 'nullable|string|max:120',
            'settings.name_zh'        => 'nullable|string|max:120',
            'settings.description_uz' => 'nullable|string|max:255',
            'settings.description_ru' => 'nullable|string|max:255',
            'settings.description_zh' => 'nullable|string|max:255',
            'settings.market_url'     => 'nullable|url:https,http|max:500',
            'settings.is_active'      => 'boolean',
        ];
    }

    public function saveSettings(): void
    {
        $data = $this->validate(self::settingsRules())['settings'];

        // the icon is not edited here: the stored one stays
        $this->category->update(collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all());
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
        $this->dispatch('saved');
    }

    public function toggle(int $id): void
    {
        $offer = $this->category->offers()->findOrFail($id);
        $offer->update(['is_active' => !$offer->is_active]);
        $this->dispatch('notify', type: 'success', message: __('admin.list.status_changed'));
    }

    public function move(int $id, int $dir): void
    {
        $items = $this->category->offers()->get()->values();
        $i = $items->search(fn ($o) => $o->id === $id);
        $j = $i + $dir;
        if ($i === false || !isset($items[$j])) {
            return;
        }
        [$a, $b] = [$items[$i], $items[$j]];
        $order = $items->pluck('id')->all();
        [$order[$i], $order[$j]] = [$b->id, $a->id];
        foreach ($order as $pos => $offerId) {
            Offer::whereKey($offerId)->update(['sort_order' => $pos]);
        }
    }

    public function delete(int $id): void
    {
        $offer = $this->category->offers()->findOrFail($id);
        if ($offer->image_path) {
            Storage::disk('public')->delete($offer->image_path);
        }
        $offer->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'missing');
    }

    public function render(LabelPdfService $pdf)
    {
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'items';
        $isTemplates = $this->category->isTemplates();
        $templates = $isTemplates && $tab === 'items' ? LabelTemplate::orderByDesc('is_default')->orderBy('name')->get() : collect();

        $all = $isTemplates ? collect() : $this->category->offers()->get();
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $q = mb_strtolower(trim($this->search));
        $state = fn (Offer $o) => $o->is_active ? 'published' : 'hidden';
        $items = $all
            ->when($status !== 'all', fn ($c) => $c->filter(fn (Offer $o) => $state($o) === $status))
            ->when($this->missing, fn ($c) => $c->filter(fn (Offer $o) => $o->translationIncomplete()))
            ->when($q !== '', fn ($c) => $c->filter(fn (Offer $o) => collect(AdminI18n::locales())
                ->contains(fn ($l) => str_contains(mb_strtolower((string) $o->{"title_$l"}), $q))))
            ->values();
        $missingCount = $all->filter(fn (Offer $o) => $o->translationIncomplete())->count();
        $rules = self::settingsRules();

        return view('livewire.admin.offer-category-manager', [
            'tab'          => $tab,
            'items'        => $items,
            'total'        => $all->count(),
            // the order is changed in the whole list only: with a filter the neighbours are not the real ones
            'canMove'      => $items->count() === $all->count(),
            'positions'    => $all->pluck('id')->flip(),
            'statuses'     => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'label' => $s === 'all' ? __('common.all') : __("admin.status.$s"),
                'count' => $s === 'all' ? $all->count() : $all->filter(fn (Offer $o) => $state($o) === $s)->count(),
            ]])->all(),
            'missingCount' => $missingCount,
            'tabs'         => [
                'items'    => ['label' => $isTemplates ? __('nav.templates') : __('nav.products'), 'href' => route('admin.offers', $this->category),
                    'count' => $isTemplates ? null : $all->count(), 'gap' => $missingCount],
                'settings' => ['label' => __('nav.settings'), 'href' => route('admin.offers', [$this->category, 'tab' => 'settings']),
                    'gap' => (int) AdminI18n::isIncomplete($this->category, OfferCategory::TRANSLATABLE)],
            ],
            'templates'    => $templates,
            'previews'     => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)]),
            'shown'        => OfferCategory::SHOWN_ITEMS,
            'limits'       => ['name' => AdminI18n::max($rules, 'settings.name_uz'), 'description' => AdminI18n::max($rules, 'settings.description_uz')],
        ]);
    }
}
