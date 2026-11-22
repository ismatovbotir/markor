<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use App\Domain\Offers\Models\OfferCategory;
use App\Support\AdminI18n;
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

    public OfferCategory $category;
    public ?int $offerId = null;
    public array $item = [];
    public array $driverIds = [];
    public $addDriver = '';
    public $photo = null;
    public bool $removeImage = false;
    public string $aiNotes = '';
    public ?string $aiMessage = null;

    /** Texts written by AI that wait for the answer "overwrite what is already typed?" */
    public ?array $pendingFill = null;
    /** Labels of the filled fields the pending texts would replace (shown in the dialog). */
    public array $fillConflicts = [];

    public function mount(OfferCategory $category, ?Offer $offer = null): void
    {
        abort_if($category->isTemplates(), 404); // label sizes are label templates, not items
        $this->category = $category;
        $this->item = ['slug' => '', 'url' => '', 'action' => 'order', 'is_active' => true, 'image_path' => null, 'faq' => []];
        foreach (Offer::TRANSLATABLE as $field) {
            foreach (AdminI18n::locales() as $l) {
                $this->item["{$field}_$l"] = '';
            }
        }

        if ($offer?->exists) {
            abort_unless($offer->offer_category_id === $category->id, 404);
            $this->offerId = $offer->id;
            foreach (array_keys($this->item) as $k) {
                $this->item[$k] = $offer->$k ?? $this->item[$k];
            }
            $this->item['faq'] = collect($offer->faq ?? [])->map(fn ($row) => array_merge($this->emptyFaqRow(), array_intersect_key((array) $row, $this->emptyFaqRow())))->values()->all();
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

    public function updatedAddDriver($id): void
    {
        if ($id && PrinterDriver::whereKey($id)->exists() && !in_array((int) $id, $this->driverIds, true)) {
            $this->driverIds[] = (int) $id;
            $this->dispatch('form-changed');
        }
        $this->addDriver = '';
    }

    public function removeDriver(int $id): void
    {
        $this->driverIds = array_values(array_diff($this->driverIds, [$id]));
        $this->dispatch('form-changed');
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
        $ext = implode(',', config('uploads.image.extensions'));

        return ['photo' => ['nullable', 'image', 'max:' . config('uploads.image.max_kb'), 'extensions:' . $ext, 'mimes:' . $ext]];
    }

    private function emptyFaqRow(): array
    {
        $row = [];
        foreach (AdminI18n::locales() as $l) {
            foreach (Offer::FAQ_PARTS as $part) {
                $row["{$part}_$l"] = '';
            }
        }

        return $row;
    }

    public function addFaq(): void
    {
        if (count($this->item['faq']) < Offer::FAQ_MAX) {
            $this->item['faq'][] = $this->emptyFaqRow();
            $this->dispatch('form-changed');
        }
    }

    public function removeFaq(int $i): void
    {
        unset($this->item['faq'][$i]);
        $this->item['faq'] = array_values($this->item['faq']);
        $this->resetErrorBag(); // row numbers moved: the old messages would point at other rows
        $this->dispatch('form-changed');
    }

    /**
     * Writes names, descriptions and FAQ (uz/ru/zh) with Claude. Only empty fields are filled; when a description or
     * the FAQ already has text, the result waits (pendingFill) and the form asks what to do. A typed name is never replaced.
     */
    public function generateContent(\App\Domain\Ai\OfferWriter $writer, \App\Domain\Ai\Claude $claude): void
    {
        $this->cancelFill();
        $title = collect(AdminI18n::locales())->map(fn ($l) => $this->item["title_$l"] ?? '')->first(fn ($t) => filled($t));
        if (!$title) {
            $this->aiMessage = __('ai.title_first');

            return;
        }
        if (!$claude->available()) {
            $this->aiMessage = __('ai.unavailable.' . $claude->unavailableReason());

            return;
        }
        set_time_limit(180);

        $result = $writer->write([
            'title' => $title, 'category' => $this->category->name_uz . ' / ' . $this->category->name_ru,
            'url' => $this->item['url'] ?? null, 'notes' => $this->aiNotes,
        ]);
        if (!$result) {
            $this->aiMessage = __('ai.failed') . ($claude->available() ? '' : ' ' . __('ai.unavailable.' . $claude->unavailableReason()));

            return;
        }

        $this->pendingFill = $result;
        $this->fillConflicts = $this->conflicts($result);
        if (!$this->fillConflicts) {
            $this->applyFill(false);
        }
    }

    /** Filled fields the AI texts would replace: ["Tavsif · RU", "Savol-javob (FAQ) · 3"]. */
    private function conflicts(array $result): array
    {
        $labels = [];
        foreach (AdminI18n::locales() as $l) {
            $now = trim((string) ($this->item["description_$l"] ?? ''));
            $new = trim((string) ($result["description_$l"] ?? ''));
            if ($now !== '' && $new !== '' && $now !== $new) {
                $labels[] = __('offers.description') . ' · ' . strtoupper($l);
            }
        }
        if ($this->faqHasText() && !empty($result['faq'])) {
            $labels[] = __('ai.faq') . ' · ' . count($this->item['faq']);
        }

        return $labels;
    }

    private function faqHasText(): bool
    {
        return collect($this->item['faq'])->contains(fn ($row) => collect($row)->contains(fn ($v) => filled($v)));
    }

    /** Puts the waiting AI texts into the form: empty fields only, or ($overwrite) also over descriptions and the FAQ. */
    public function applyFill(bool $overwrite = false): void
    {
        $result = $this->pendingFill;
        $this->cancelFill();
        if (!$result) {
            return;
        }
        foreach (AdminI18n::locales() as $l) {
            // a name the admin typed always stays
            if (blank($this->item["title_$l"] ?? null) && filled($result["title_$l"] ?? null)) {
                $this->item["title_$l"] = $result["title_$l"];
            }
            if (filled($result["description_$l"] ?? null) && ($overwrite || blank($this->item["description_$l"] ?? null))) {
                $this->item["description_$l"] = $result["description_$l"];
            }
        }
        if (!empty($result['faq']) && ($overwrite || !$this->faqHasText())) {
            $this->item['faq'] = collect($result['faq'])->take(Offer::FAQ_MAX)
                ->map(fn ($row) => array_merge($this->emptyFaqRow(), array_intersect_key((array) $row, $this->emptyFaqRow())))->values()->all();
        }
        if (!$this->offerId) {
            $this->item['slug'] = Str::slug($this->item['title_uz']);
        }
        $this->aiMessage = '✓ ' . __('ai.generated');
        $this->dispatch('form-changed');
    }

    public function cancelFill(): void
    {
        $this->pendingFill = null;
        $this->fillConflicts = [];
        $this->aiMessage = null;
    }

    public function save()
    {
        $rules = collect(Offer::textRules())->mapWithKeys(fn ($rule, $key) => ["item.$key" => $rule])->all() + [
            'item.url'       => 'nullable|url:https,http|max:500',
            'item.action'    => 'required|in:call,order',
            'item.is_active' => 'boolean',
            'item.faq'       => 'nullable|array|max:' . Offer::FAQ_MAX,
            'driverIds'      => 'array', 'driverIds.*' => 'integer|exists:printer_drivers,id',
        ] + $this->photoRules();

        // a question is shown on the site only with its Uzbek text: such a row stops the save, it is never dropped silently
        $this->withValidator(fn ($validator) => $validator->after(function ($validator) {
            foreach ($this->item['faq'] ?? [] as $i => $row) {
                if (blank($row['q_uz'] ?? null) || blank($row['a_uz'] ?? null)) {
                    $validator->errors()->add("item.faq.$i", __('admin.i18n.row_uz_required'));
                }
            }
        }));
        $data = $this->validate($rules);

        // the page address is never edited: set once from the Uzbek name, links and search results keep working
        $values = collect($data['item'])->except(['image_path', 'slug'])->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
        $values['faq'] = array_values($values['faq'] ?? []) ?: null;

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
            // stay on the page: translating means several saves in a row
            $offer->update($values);
            $offer->drivers()->sync($this->driverIds);
            $this->item['image_path'] = $offer->image_path;
            $this->reset('photo', 'removeImage');
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            $this->dispatch('saved');

            return null;
        }

        // a new product continues on its own edit address
        $offer = $this->category->offers()->create($values + [
            'slug' => Offer::freeSlug($values['title_uz']),
            'category' => $this->category->slug, 'icon' => $this->category->icon,
            'sort_order' => (int) $this->category->offers()->max('sort_order') + 1,
        ]);
        $offer->drivers()->sync($this->driverIds);
        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.offers.edit', [$this->category, $offer], navigate: false);
    }

    public function render()
    {
        $drivers = PrinterDriver::orderBy('brand')->orderBy('sort_order')->get(['id', 'brand', 'model', 'slug']);
        $image = $this->photo && !$this->getErrorBag()->has('photo') ? $this->photo->temporaryUrl()
            : (!$this->removeImage && $this->item['image_path'] ? Offer::publicUrl($this->item['image_path']) : null);
        $rules = Offer::textRules();

        return view('livewire.admin.offer-form', [
            'offer'         => $this->offerId ? Offer::find($this->offerId) : null,
            'chosenDrivers' => collect($this->driverIds)->map(fn ($id) => $drivers->firstWhere('id', $id))->filter()->values(),
            'driverChoices' => $drivers->whereNotIn('id', $this->driverIds)->groupBy('brand'),
            'image'         => $image,
            'imageSource'   => $this->photo ? 'upload' : ($image ? 'own' : null),
            'ai'            => app(\App\Domain\Ai\Claude::class),
            'limits'        => ['title' => AdminI18n::max($rules, 'title_uz'), 'description' => AdminI18n::max($rules, 'description_uz'),
                'q' => AdminI18n::max($rules, 'faq.*.q_uz'), 'a' => AdminI18n::max($rules, 'faq.*.a_uz'), 'faq' => Offer::FAQ_MAX],
        ]);
    }
}
