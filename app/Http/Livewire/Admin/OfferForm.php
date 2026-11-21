<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Super admin: create / edit one marketplace product on its own page (texts, link, photo, FAQ, drivers). No price field: prices are given on request. */
#[Layout('layouts.app')]
class OfferForm extends Component
{
    use WithFileUploads;

    public const LOCALES = ['uz' => "O'zbekcha", 'ru' => 'Русский', 'zh' => '中文'];

    public OfferCategory $category;
    public ?int $offerId = null;
    public array $item = [];
    public array $driverIds = [];
    public $addDriver = '';
    public $photo = null;
    public bool $removeImage = false;
    public string $locale = 'uz';
    public string $aiNotes = '';
    public ?string $aiMessage = null;

    public function mount(OfferCategory $category, ?Offer $offer = null): void
    {
        abort_if($category->isTemplates(), 404); // label sizes are label templates, not items
        $this->category = $category;
        $this->item = ['slug' => '', 'title_uz' => '', 'title_ru' => '', 'title_zh' => '', 'description_uz' => '', 'description_ru' => '', 'description_zh' => '',
            'url' => '', 'action' => 'order', 'is_active' => true, 'image_path' => null, 'faq' => []];

        if ($offer?->exists) {
            abort_unless($offer->offer_category_id === $category->id, 404);
            $this->offerId = $offer->id;
            foreach (array_keys($this->item) as $k) {
                $this->item[$k] = $offer->$k ?? $this->item[$k];
            }
            $this->item['faq'] = $offer->faq ?? [];
            $this->item['is_active'] = (bool) $offer->is_active;
            $this->driverIds = $offer->drivers()->pluck('printer_drivers.id')->all();
        }
    }

    /** New item: the address preview follows the Uzbek name (it is fixed once the item is saved). */
    public function updatedItem($value, $key): void
    {
        if ($key === 'title_uz' && !$this->offerId) {
            $this->item['slug'] = Str::slug($value);
        }
    }

    /** A free address from the Uzbek name: "godex-g500u", then "godex-g500u-2" … */
    private static function freeSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'item';
        $slug = $base;
        for ($i = 2; Offer::where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    public function updatedAddDriver($id): void
    {
        if ($id && PrinterDriver::whereKey($id)->exists() && !in_array((int) $id, $this->driverIds, true)) {
            $this->driverIds[] = (int) $id;
        }
        $this->addDriver = '';
    }

    public function removeDriver(int $id): void
    {
        $this->driverIds = array_values(array_diff($this->driverIds, [$id]));
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
        $ext = implode(',', config('uploads.image.extensions'));

        return ['photo' => ['nullable', 'image', 'max:' . config('uploads.image.max_kb'), 'extensions:' . $ext, 'mimes:' . $ext]];
    }

    public function addFaq(): void
    {
        if (count($this->item['faq']) < 8) {
            $this->item['faq'][] = ['q_uz' => '', 'a_uz' => '', 'q_ru' => '', 'a_ru' => '', 'q_zh' => '', 'a_zh' => ''];
        }
    }

    public function removeFaq(int $i): void
    {
        unset($this->item['faq'][$i]);
        $this->item['faq'] = array_values($this->item['faq']);
    }

    /** Fills names, descriptions and FAQ (uz/ru/zh) with Claude; the admin reviews and saves. */
    public function generateContent(\App\Domain\Ai\OfferWriter $writer, \App\Domain\Ai\Claude $claude): void
    {
        $title = collect([$this->item['title_uz'] ?? '', $this->item['title_ru'] ?? '', $this->item['title_zh'] ?? ''])->first(fn ($t) => filled($t));
        if (!$title) {
            $this->addError('item.title_uz', __('ai.title_first'));

            return;
        }
        if (!$claude->available()) {
            $this->aiMessage = __('ai.unavailable.' . $claude->unavailableReason());

            return;
        }
        set_time_limit(180);

        $result = $writer->write([
            'title' => $title, 'category' => $this->category->name_uz . ' / ' . $this->category->name_ru,
            'price' => null, 'url' => $this->item['url'] ?? null, 'notes' => $this->aiNotes,
        ]);
        if (!$result) {
            $this->aiMessage = __('ai.failed') . ($claude->available() ? '' : ' ' . __('ai.unavailable.' . $claude->unavailableReason()));

            return;
        }
        // a name the admin already typed stays; everything else is filled in
        foreach (array_keys(self::LOCALES) as $l) {
            if (blank($this->item["title_$l"] ?? null)) {
                $this->item["title_$l"] = $result["title_$l"];
            }
            $this->item["description_$l"] = $result["description_$l"];
        }
        $this->item['faq'] = $result['faq'];
        if (!$this->offerId) {
            $this->item['slug'] = Str::slug($this->item['title_uz']);
        }
        $this->aiMessage = '✓ ' . __('ai.generated');
    }

    public function save()
    {
        $data = $this->validate([
            'item.title_uz'       => 'required|string|max:160',
            'item.title_ru'       => 'nullable|string|max:160',
            'item.title_zh'       => 'nullable|string|max:160',
            'item.description_uz' => 'nullable|string|max:400',
            'item.description_ru' => 'nullable|string|max:400',
            'item.description_zh' => 'nullable|string|max:400',
            'item.url'            => 'nullable|url:https,http|max:500',
            'item.action'         => 'required|in:call,order',
            'item.is_active'      => 'boolean',
            'item.faq'            => 'nullable|array|max:8',
            'item.faq.*.q_uz'     => 'nullable|string|max:200', 'item.faq.*.a_uz' => 'nullable|string|max:600',
            'item.faq.*.q_ru'     => 'nullable|string|max:200', 'item.faq.*.a_ru' => 'nullable|string|max:600',
            'item.faq.*.q_zh'     => 'nullable|string|max:200', 'item.faq.*.a_zh' => 'nullable|string|max:600',
            'driverIds'           => 'array', 'driverIds.*' => 'integer|exists:printer_drivers,id',
        ] + $this->photoRules());

        // the page address is never edited: set once from the Uzbek name, links and search results keep working
        $values = collect($data['item'])->except(['image_path', 'slug'])->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
        // keep only questions that have an Uzbek question and answer
        $values['faq'] = collect($values['faq'] ?? [])->filter(fn ($f) => filled($f['q_uz'] ?? null) && filled($f['a_uz'] ?? null))->values()->all() ?: null;

        $offer = $this->offerId ? $this->category->offers()->findOrFail($this->offerId) : null;
        $old = $offer?->image_path;
        if ($this->photo) {
            $values['image_path'] = $this->photo->store('offers', 'public');
        } elseif ($this->removeImage) {
            $values['image_path'] = null;
        }
        if (array_key_exists('image_path', $values) && $old) {
            Storage::disk('public')->delete($old);
        }

        if ($offer) {
            $offer->update($values);
        } else {
            $offer = $this->category->offers()->create($values + [
                'slug' => self::freeSlug($values['title_uz']),
                'category' => $this->category->slug, 'icon' => $this->category->icon,
                'sort_order' => (int) $this->category->offers()->max('sort_order') + 1,
            ]);
        }
        $offer->drivers()->sync($this->driverIds);

        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.offers', $this->category, navigate: false);
    }

    public function render()
    {
        $drivers = PrinterDriver::orderBy('brand')->orderBy('sort_order')->get(['id', 'brand', 'model', 'slug']);
        $image = $this->photo && !$this->getErrorBag()->has('photo') ? $this->photo->temporaryUrl()
            : (!$this->removeImage && $this->item['image_path'] ? Offer::publicUrl($this->item['image_path']) : null);

        return view('livewire.admin.offer-form', [
            'chosenDrivers' => collect($this->driverIds)->map(fn ($id) => $drivers->firstWhere('id', $id))->filter()->values(),
            'driverChoices' => $drivers->whereNotIn('id', $this->driverIds)->groupBy('brand'),
            'image'         => $image,
            'imageSource'   => $this->photo ? 'upload' : ($image ? 'own' : null),
            'ai'            => app(\App\Domain\Ai\Claude::class),
        ]);
    }
}
