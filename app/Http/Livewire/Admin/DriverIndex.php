<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: printer driver pages (/drivers). No files: each model links to the official download page. */
#[Layout('layouts.app')]
class DriverIndex extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public array $item = [];
    public string $search = '';

    private function blank(): array
    {
        return ['brand' => '', 'model' => '', 'kind' => 'desktop', 'language' => '', 'os' => ['windows'], 'driver_url' => '',
            'summary_uz' => '', 'summary_ru' => '', 'summary_zh' => '', 'notes_uz' => '', 'notes_ru' => '', 'notes_zh' => '', 'is_active' => true];
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->editingId = null;
        $this->item = $this->blank();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $driver = PrinterDriver::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $id;
        $this->item = array_merge($this->blank(), $driver->only(array_keys($this->blank())), ['os' => $driver->os ?? []]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'item.brand'      => 'required|string|max:60',
            'item.model'      => 'required|string|max:80',
            'item.kind'       => 'required|in:' . implode(',', PrinterDriver::KINDS),
            'item.language'   => 'nullable|string|max:40',
            'item.os'         => 'array',
            'item.os.*'       => 'in:' . implode(',', PrinterDriver::OS),
            'item.driver_url' => ['required', 'url:https', 'max:500'], // official page only, https
            'item.summary_uz' => 'nullable|string|max:300', 'item.summary_ru' => 'nullable|string|max:300', 'item.summary_zh' => 'nullable|string|max:300',
            'item.notes_uz'   => 'nullable|string|max:3000', 'item.notes_ru' => 'nullable|string|max:3000', 'item.notes_zh' => 'nullable|string|max:3000',
            'item.is_active'  => 'boolean',
        ])['item'];

        $values = collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
        $values['os'] = array_values(array_intersect(PrinterDriver::OS, $data['os'] ?? []));
        $slug = Str::slug($values['brand'] . ' ' . str_replace('/', ' ', $values['model']));
        if (PrinterDriver::where('slug', $slug)->where('id', '!=', $this->editingId ?? 0)->exists()) {
            $this->addError('item.model', __('drivers.duplicate'));

            return;
        }
        $values['slug'] = $slug;

        if ($this->editingId) {
            PrinterDriver::findOrFail($this->editingId)->update($values);
        } else {
            PrinterDriver::create($values + ['sort_order' => (int) PrinterDriver::max('sort_order') + 1]);
        }
        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function toggle(int $id): void
    {
        $driver = PrinterDriver::findOrFail($id);
        $driver->update(['is_active' => !$driver->is_active]);
    }

    public function delete(int $id): void
    {
        PrinterDriver::findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        $drivers = PrinterDriver::query()
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('brand', 'like', '%' . trim($this->search) . '%')->orWhere('model', 'like', '%' . trim($this->search) . '%')))
            ->orderBy('brand')->orderBy('sort_order')->get();

        return view('livewire.admin.driver-index', ['drivers' => $drivers]);
    }
}
