<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\Banner;
use App\Domain\Offers\Models\Offer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Super admin: create / edit one home page banner, with a live preview. The default banner is not edited here. */
#[Layout('layouts.app')]
class BannerForm extends Component
{
    use WithFileUploads;

    public const LOCALES = ['uz' => "O'zbekcha", 'ru' => 'Русский', 'zh' => '中文'];
    public const LIMITS = ['title' => 160, 'text' => 400, 'button' => 60];

    public ?int $bannerId = null;
    public array $item = [];
    public $photo = null;
    public bool $removeImage = false;
    public string $locale = 'uz';   // language tab of the texts and the preview

    public function mount(?Banner $banner = null): void
    {
        abort_if($banner?->is_default, 404); // the built-in hero takes its texts from the lang files

        $this->item = ['title_uz' => '', 'title_ru' => '', 'title_zh' => '', 'text_uz' => '', 'text_ru' => '', 'text_zh' => '',
            'button_uz' => '', 'button_ru' => '', 'button_zh' => '', 'url' => '', 'offer_id' => null, 'is_sponsored' => false,
            'style' => 'light', 'is_active' => true, 'starts_at' => '', 'ends_at' => '', 'image_path' => null];

        if ($banner?->exists) {
            $this->bannerId = $banner->id;
            $this->item = array_merge($this->item, $banner->only(array_keys($this->item)), [
                'starts_at' => $banner->starts_at?->toDateString() ?? '', 'ends_at' => $banner->ends_at?->toDateString() ?? '',
            ]);
        }
    }

    public function updatedItem($value, $key): void
    {
        if ($key === 'offer_id') {
            $this->fillFromOffer($value);
        }
    }

    /** Picking a marketplace product fills the texts and the link from it (all still editable). */
    public function fillFromOffer($id): void
    {
        $offer = $id ? Offer::find($id) : null;
        $this->item['offer_id'] = $offer?->id;
        if (!$offer) {
            return;
        }
        foreach (array_keys(self::LOCALES) as $l) {
            $this->item["title_$l"] = Str::limit((string) $offer->{"title_$l"}, 157);
            // the first sentence reads best on a banner; the whole text is on the product page
            $text = trim((string) $offer->{"description_$l"});
            $this->item["text_$l"] = Str::limit(preg_split('/(?<=[.!?。])\s*/u', $text, 2)[0] ?: $text, 397);
        }
        $this->item['url'] = '/marketplace/' . $offer->slug;
    }

    public function updatedPhoto(): void
    {
        $this->removeImage = false;
        $this->validateOnly('photo', $this->photoRules());
    }

    public function dropImage(): void
    {
        $this->photo = null;
        $this->removeImage = true;
    }

    private function photoRules(): array
    {
        return ['photo' => ['nullable', 'image', 'max:3072', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp']];
    }

    public function save()
    {
        $data = $this->validate([
            'item.title_uz' => 'required|string|max:160', 'item.title_ru' => 'nullable|string|max:160', 'item.title_zh' => 'nullable|string|max:160',
            'item.text_uz' => 'nullable|string|max:400', 'item.text_ru' => 'nullable|string|max:400', 'item.text_zh' => 'nullable|string|max:400',
            'item.button_uz' => 'nullable|string|max:60', 'item.button_ru' => 'nullable|string|max:60', 'item.button_zh' => 'nullable|string|max:60',
            'item.url'       => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)#i'],
            'item.offer_id'  => 'nullable|integer|exists:offers,id',
            'item.style'     => 'required|in:' . implode(',', Banner::STYLES),
            'item.is_active' => 'boolean',
            'item.is_sponsored' => 'boolean',
            'item.starts_at' => 'nullable|date', 'item.ends_at' => 'nullable|date|after_or_equal:item.starts_at',
        ] + $this->photoRules())['item'];

        $values = collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->except('image_path')->all();
        $banner = $this->bannerId ? Banner::where('is_default', false)->findOrFail($this->bannerId) : null;
        $old = $banner?->image_path;

        if ($this->photo) {
            $values['image_path'] = $this->photo->store('banners', 'public');
        } elseif ($this->removeImage) {
            $values['image_path'] = null;
        }
        // an uploaded picture that is replaced or removed is deleted; seeded ones (public/images/…) ship with the code
        if (array_key_exists('image_path', $values) && $old && !str_starts_with($old, 'images/')) {
            Storage::disk('public')->delete($old);
        }

        if ($banner) {
            $banner->update($values);
        } else {
            Banner::create($values + ['sort_order' => (int) Banner::max('sort_order') + 1]);
        }

        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.banners', navigate: false);
    }

    public function render()
    {
        $offers = Offer::with('offerCategory')->orderBy('category')->orderBy('sort_order')
            ->get(['id', 'slug', 'title_uz', 'category', 'offer_category_id', 'image_path', 'is_active']);
        $offer = $offers->firstWhere('id', (int) ($this->item['offer_id'] ?? 0));

        // the preview is an unsaved banner with the current form values
        $preview = new Banner(collect($this->item)->except('image_path')->all());
        $preview->image_path = $this->removeImage ? null : ($this->item['image_path'] ?? null);
        $preview->setRelation('offer', $offer);
        $image = $this->photo && !$this->getErrorBag()->has('photo') ? $this->photo->temporaryUrl() : $preview->imageUrl();

        return view('livewire.admin.banner-form', [
            'offers' => $offers, 'offer' => $offer, 'preview' => $preview, 'previewImage' => $image,
            // product banner: the product photo by default, or the banner's own picture
            'productImage' => Offer::publicUrl($offer?->image_path),
            'ownImage' => $this->photo && !$this->getErrorBag()->has('photo') ? $image
                : ($preview->image_path ? $preview->imageUrl() : null),
            'imageSource' => $this->photo ? 'upload' : ($preview->image_path ? 'own' : ($image ? 'product' : null)),
        ]);
    }
}
