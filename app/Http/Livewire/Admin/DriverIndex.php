<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: printer driver pages (/drivers) list. No files: each model links to the official download page. Create / edit: DriverForm. */
#[Layout('layouts.app')]
class DriverIndex extends Component
{
    public string $search = '';

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
        $drivers = PrinterDriver::query()->with('offers:id,title_uz')
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('brand', 'like', '%' . trim($this->search) . '%')->orWhere('model', 'like', '%' . trim($this->search) . '%')))
            ->orderBy('brand')->orderBy('sort_order')->get();

        return view('livewire.admin.driver-index', ['drivers' => $drivers]);
    }
}
