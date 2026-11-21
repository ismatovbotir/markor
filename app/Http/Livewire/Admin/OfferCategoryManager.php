<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Super admin: one "Biz taklif qilamiz" category — its settings, market link and items. */
#[Layout('layouts.app')]
class OfferCategoryManager extends Component
{
    use WithFileUploads;

    public OfferCategory $category;

    /** category settings */
    public array $settings = [];

    /** item form */
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $item = [];
    public $photo = null;

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

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'photo']);
        $this->dispatch('offer-photo-cleared');
        $this->item = ['title_uz' => '', 'title_ru' => '', 'title_zh' => '', 'description_uz' => '', 'description_ru' => '', 'description_zh' => '',
            'price' => '', 'url' => '', 'action' => 'order', 'is_active' => true, 'image_path' => null];
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $offer = $this->category->offers()->findOrFail($id);
        $this->resetValidation();
        $this->photo = null;
        $this->dispatch('offer-photo-cleared');
        $this->editingId = $id;
        $this->item = $offer->only(['title_uz', 'title_ru', 'title_zh', 'description_uz', 'description_ru', 'description_zh', 'price', 'url', 'action', 'is_active', 'image_path']);
        $this->showForm = true;
    }

    public function saveItem(): void
    {
        $data = $this->validate([
            'item.title_uz'       => 'required|string|max:160',
            'item.title_ru'       => 'nullable|string|max:160',
            'item.title_zh'       => 'nullable|string|max:160',
            'item.description_uz' => 'nullable|string|max:400',
            'item.description_ru' => 'nullable|string|max:400',
            'item.description_zh' => 'nullable|string|max:400',
            'item.price'          => 'nullable|string|max:60',
            'item.url'            => 'nullable|url:https,http|max:500',
            'item.action'         => 'required|in:call,order',
            'item.is_active'      => 'boolean',
            'photo'               => ['nullable', 'image', 'max:' . config('uploads.image.max_kb'), 'extensions:' . implode(',', config('uploads.image.extensions')), 'mimes:' . implode(',', config('uploads.image.extensions'))],
        ]);
        $values = collect($data['item'])->except('image_path')->all();

        if ($this->photo) {
            $values['image_path'] = $this->photo->store('offers', 'public');
        }

        if ($this->editingId) {
            $offer = $this->category->offers()->findOrFail($this->editingId);
            if (isset($values['image_path']) && $offer->image_path) {
                Storage::disk('public')->delete($offer->image_path);
            }
            $offer->update($values);
        } else {
            $this->category->offers()->create($values + [
                'slug'       => $this->category->slug . '-' . Str::lower(Str::random(6)),
                'category'   => $this->category->slug,
                'icon'       => $this->category->icon,
                'sort_order' => (int) $this->category->offers()->max('sort_order') + 1,
            ]);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function removePhoto(): void
    {
        $offer = $this->category->offers()->findOrFail($this->editingId);
        if ($offer->image_path) {
            Storage::disk('public')->delete($offer->image_path);
            $offer->update(['image_path' => null]);
        }
        $this->item['image_path'] = null;
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
