<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\PrinterDriver;
use App\Support\AdminI18n;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super admin: printer driver pages (/drivers) list. No files: each model links to the official download page. Create / edit: DriverForm. */
#[Layout('layouts.app')]
class DriverIndex extends Component
{
    public const STATUSES = ['all', 'published', 'hidden'];

    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    /** Only drivers with an empty ru / zh text (the menu badge opens the list with ?missing=1). */
    #[Url(except: false)]
    public bool $missing = false;

    public function toggle(int $id): void
    {
        $driver = PrinterDriver::findOrFail($id);
        $driver->update(['is_active' => !$driver->is_active]);
        $this->dispatch('notify', type: 'success', message: __('admin.list.status_changed'));
    }

    public function delete(int $id): void
    {
        PrinterDriver::findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'missing');
    }

    public function render()
    {
        $all = PrinterDriver::query()->with('offers:id,title_uz')->orderBy('brand')->orderBy('sort_order')->get();
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $q = mb_strtolower(trim($this->search));
        $state = fn (PrinterDriver $d) => $d->is_active ? 'published' : 'hidden';
        $incomplete = fn (PrinterDriver $d) => AdminI18n::isIncomplete($d, PrinterDriver::TRANSLATABLE);

        $drivers = $all
            ->when($status !== 'all', fn ($c) => $c->filter(fn (PrinterDriver $d) => $state($d) === $status))
            ->when($this->missing, fn ($c) => $c->filter($incomplete))
            ->when($q !== '', fn ($c) => $c->filter(fn (PrinterDriver $d) => str_contains(mb_strtolower($d->brand . ' ' . $d->model), $q)))
            ->values();

        return view('livewire.admin.driver-index', [
            'drivers' => $drivers,
            'total' => $all->count(),
            'statuses' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'label' => $s === 'all' ? __('common.all') : __("admin.status.$s"),
                'count' => $s === 'all' ? $all->count() : $all->filter(fn (PrinterDriver $d) => $state($d) === $s)->count(),
            ]])->all(),
            'missingCount' => $all->filter($incomplete)->count(),
        ]);
    }
}
