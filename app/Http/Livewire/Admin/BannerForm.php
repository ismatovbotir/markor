<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\Banner;
use App\Domain\Offers\Models\Offer;
use App\Support\AdminI18n;
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

    public ?int $bannerId = null;
    public array $item = [];
    public $photo = null;
    public bool $removeImage = false;
    /** Language of the server-rendered preview; follows the language switch of the form (set from the page, never the other way). */
    public string $locale = AdminI18n::SOURCE;

    /** Texts of the chosen product that wait for the answer "overwrite what is already typed?" */
    public ?array $pendingFill = null;
    /** Labels of the filled fields the product texts would replace (shown in the dialog). */
    public array $fillConflicts = [];

    public function mount(?Banner $banner = null): void
    {
        abort_if($banner?->is_default, 404); // the built-in hero takes its texts from the lang files

        $this->item = ['url' => '', 'offer_id' => null, 'is_sponsored' => false,
            'style' => 'light', 'is_active' => true, 'starts_at' => '', 'ends_at' => '', 'image_path' => null];
        foreach (Banner::TRANSLATABLE as $field) {
            foreach (AdminI18n::locales() as $l) {
                $this->item["{$field}_$l"] = '';
            }
        }

        if ($banner?->exists) {
            $this->bannerId = $banner->id;
            $this->item = array_merge($this->item, collect($banner->only(array_keys($this->item)))->map(fn ($v, $k) => $v ?? ($k === 'offer_id' || $k === 'image_path' ? null : ''))->all(), [
                'starts_at' => $banner->starts_at?->toDateString() ?? '', 'ends_at' => $banner->ends_at?->toDateString() ?? '',
                'is_active' => (bool) $banner->is_active, 'is_sponsored' => (bool) $banner->is_sponsored,
            ]);
        }
    }

    public function updatedLocale($value): void
    {
        $this->locale = in_array($value, AdminI18n::locales(), true) ? $value : AdminI18n::SOURCE;
    }

    public function updatedItem($value, $key): void
    {
        if ($key === 'offer_id') {
            $this->fillFromOffer($value);
        }
    }

    /**
     * Picking a marketplace product offers its texts and link (all still editable). Only empty fields are filled;
     * when a filled field would change, the texts wait (pendingFill) and the form asks what to do.
     */
    public function fillFromOffer($id): void
    {
        $this->cancelFill();
        $offer = $id ? Offer::find($id) : null;
        $this->item['offer_id'] = $offer?->id;
        if (!$offer) {
            return;
        }

        $texts = ['url' => '/marketplace/' . $offer->slug];
        foreach (AdminI18n::locales() as $l) {
            $texts["title_$l"] = Str::limit((string) $offer->{"title_$l"}, 157);
            // the first sentence reads best on a banner; the whole text is on the product page
            $text = trim((string) $offer->{"description_$l"});
            $texts["text_$l"] = Str::limit(preg_split('/(?<=[.!?。])\s*/u', $text, 2)[0] ?: $text, 397);
        }

        $this->pendingFill = $texts;
        $this->fillConflicts = collect($texts)
            ->filter(fn ($new, $key) => filled($this->item[$key] ?? null) && trim((string) $this->item[$key]) !== trim((string) $new))
            ->keys()->map(fn ($key) => $key === 'url' ? __('banners.link') : __('banners.' . Str::before($key, '_')) . ' · ' . strtoupper(Str::after($key, '_')))
            ->values()->all();
        if (!$this->fillConflicts) {
            $this->applyFill(false);
        }
    }

    /** Puts the waiting product texts into the form: empty fields only, or ($overwrite) over everything typed. */
    public function applyFill(bool $overwrite = false): void
    {
        $texts = $this->pendingFill;
        $this->cancelFill();
        foreach ($texts ?? [] as $key => $new) {
            if ($overwrite || (blank($this->item[$key] ?? null) && filled($new))) {
                $this->item[$key] = (string) $new;
            }
        }
        $this->dispatch('form-changed');
    }

    /** Keeps the product link and drops its waiting texts. */
    public function cancelFill(): void
    {
        $this->pendingFill = null;
        $this->fillConflicts = [];
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
        $this->dispatch('form-changed');
    }

    private function photoRules(): array
    {
        return ['photo' => ['nullable', 'image', 'max:3072', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp']];
    }

    /** Validation rules: the text limits come from Banner::LIMITS (the counters use the same numbers). */
    private function rules(): array
    {
        $rules = [];
        foreach (Banner::LIMITS as $field => $max) {
            foreach (AdminI18n::locales() as $l) {
                $rules["item.{$field}_$l"] = ($field === 'title' && $l === AdminI18n::SOURCE ? 'required' : 'nullable') . "|string|max:$max";
            }
        }

        return $rules + [
            'item.url'       => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)#i'],
            'item.offer_id'  => 'nullable|integer|exists:offers,id',
            'item.style'     => 'required|in:' . implode(',', Banner::STYLES),
            'item.is_active' => 'boolean',
            'item.is_sponsored' => 'boolean',
            'item.starts_at' => 'nullable|date', 'item.ends_at' => 'nullable|date|after_or_equal:item.starts_at',
        ] + $this->photoRules();
    }

    public function save()
    {
        $data = $this->validate($this->rules(), ['item.url.regex' => __('banners.url_error')])['item'];

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
            // stay on the page: translating means several saves in a row
            $banner->update($values);
            $this->item['image_path'] = $banner->image_path;
            $this->reset('photo', 'removeImage');
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            $this->dispatch('saved');

            return null;
        }

        // a new banner continues on its own edit address
        $banner = Banner::create($values + ['sort_order' => (int) Banner::max('sort_order') + 1]);
        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.banners.edit', $banner, navigate: false);
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
            'banner' => $this->bannerId ? Banner::find($this->bannerId) : null,
            'limits' => Banner::LIMITS,
            'offers' => $offers, 'offer' => $offer, 'preview' => $preview, 'previewImage' => $image,
            // product banner: the product photo by default, or the banner's own picture
            'productImage' => Offer::publicUrl($offer?->image_path),
            'ownImage' => $this->photo && !$this->getErrorBag()->has('photo') ? $image
                : ($preview->image_path ? $preview->imageUrl() : null),
            'imageSource' => $this->photo ? 'upload' : ($preview->image_path ? 'own' : ($image ? 'product' : null)),
        ]);
    }
}
