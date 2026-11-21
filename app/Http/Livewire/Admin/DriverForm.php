<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use App\Domain\Offers\Models\Offer;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: one driver page — name, official download link and the marketplace products it fits. */
#[Layout('layouts.app')]
class DriverForm extends Component
{
    public ?int $driverId = null;
    public array $item = [];
    public array $offerIds = [];   // linked products, in the order they were added
    public $addOffer = '';

    public function mount(?PrinterDriver $driver = null): void
    {
        $this->item = ['brand' => '', 'model' => '', 'driver_url' => '', 'kind' => 'desktop', 'language' => '', 'os' => ['windows'],
            'summary_uz' => '', 'summary_ru' => '', 'summary_zh' => '', 'notes_uz' => '', 'notes_ru' => '', 'notes_zh' => '', 'is_active' => true];

        if ($driver?->exists) {
            $this->driverId = $driver->id;
            $this->item = array_merge($this->item, $driver->only(array_keys($this->item)), ['os' => $driver->os ?? []]);
            $this->offerIds = $driver->offers()->pluck('offers.id')->all();
        }
    }

    public function updatedAddOffer($id): void
    {
        if ($id && Offer::whereKey($id)->exists() && !in_array((int) $id, $this->offerIds, true)) {
            $this->offerIds[] = (int) $id;
        }
        $this->addOffer = '';
    }

    public function removeOffer(int $id): void
    {
        $this->offerIds = array_values(array_diff($this->offerIds, [$id]));
    }

    public function save()
    {
        $data = $this->validate([
            'item.brand'      => 'required|string|max:60',
            'item.model'      => 'nullable|string|max:80',   // optional driver name: the models are the linked products
            'item.driver_url' => ['required', 'url:https', 'max:500'], // official page only, https
            'item.kind'       => 'required|in:' . implode(',', PrinterDriver::KINDS),
            'item.language'   => 'nullable|string|max:40',
            'item.os'         => 'array',
            'item.os.*'       => 'in:' . implode(',', PrinterDriver::OS),
            'item.summary_uz' => 'nullable|string|max:300', 'item.summary_ru' => 'nullable|string|max:300', 'item.summary_zh' => 'nullable|string|max:300',
            'item.notes_uz'   => 'nullable|string|max:3000', 'item.notes_ru' => 'nullable|string|max:3000', 'item.notes_zh' => 'nullable|string|max:3000',
            'item.is_active'  => 'boolean',
            'offerIds'        => 'array', 'offerIds.*' => 'integer|exists:offers,id',
        ])['item'];

        $values = collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
        $values['os'] = array_values(array_intersect(PrinterDriver::OS, $data['os'] ?? []));
        // the address stays the same while brand and name don't change (links and search results keep working)
        $current = $this->driverId ? PrinterDriver::find($this->driverId) : null;
        $values['slug'] = $current && $current->brand === $values['brand'] && (string) $current->model === (string) $values['model']
            ? $current->slug : PrinterDriver::freeSlug($values['brand'], $values['model'], $this->driverId);

        $driver = $this->driverId
            ? tap(PrinterDriver::findOrFail($this->driverId))->update($values)
            : PrinterDriver::create($values + ['sort_order' => (int) PrinterDriver::max('sort_order') + 1]);
        $driver->offers()->sync($this->offerIds);

        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.drivers', navigate: false);
    }

    public function render()
    {
        $offers = Offer::with('offerCategory')->orderBy('category')->orderBy('sort_order')
            ->get(['id', 'slug', 'title_uz', 'category', 'offer_category_id', 'image_path', 'is_active']);
        $chosen = collect($this->offerIds)->map(fn ($id) => $offers->firstWhere('id', $id))->filter()->values();
        // printers first: the usual match; other categories stay available
        $choices = $offers->whereNotIn('id', $this->offerIds)->sortBy(fn ($o) => $o->category === 'printers' ? 0 : 1, SORT_REGULAR, false)
            ->groupBy(fn ($o) => $o->offerCategory?->name_uz ?? $o->category);

        return view('livewire.admin.driver-form', ['chosen' => $chosen, 'choices' => $choices]);
    }
}
