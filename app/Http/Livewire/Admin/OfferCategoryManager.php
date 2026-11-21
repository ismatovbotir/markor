<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: one marketplace category — its settings, market link and the items list. An item is edited on its own page (OfferForm). */
#[Layout('layouts.app')]
class OfferCategoryManager extends Component
{
    public OfferCategory $category;

    /** category settings */
    public array $settings = [];

    public function mount(OfferCategory $category): void
    {
        $this->category = $category;
        $this->settings = $category->only(['name_uz', 'name_ru', 'name_zh', 'description_uz', 'description_ru', 'description_zh', 'market_url', 'is_active', 'icon']);
    }

    public function saveSettings(): void
    {
        $data = $this->validate([
            'settings.name_uz'        => 'required|string|max:120',
            'settings.name_ru'        => 'nullable|string|max:120',
            'settings.name_zh'        => 'nullable|string|max:120',
            'settings.description_uz' => 'nullable|string|max:255',
            'settings.description_ru' => 'nullable|string|max:255',
            'settings.description_zh' => 'nullable|string|max:255',
            'settings.market_url'     => 'nullable|url:https,http|max:500',
            'settings.icon'           => 'nullable|string|max:60',
            'settings.is_active'      => 'boolean',
        ])['settings'];

        $this->category->update($data);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function toggle(int $id): void
    {
        $offer = $this->category->offers()->findOrFail($id);
        $offer->update(['is_active' => !$offer->is_active]);
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

    public function render(LabelPdfService $pdf)
    {
        $templates = $this->category->isTemplates() ? LabelTemplate::orderByDesc('is_default')->orderBy('name')->get() : collect();

        return view('livewire.admin.offer-category-manager', [
            'items'     => $this->category->offers()->get(),
            'templates' => $templates,
            'previews'  => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)]),
            'shown'     => OfferCategory::SHOWN_ITEMS,
        ]);
    }
}
