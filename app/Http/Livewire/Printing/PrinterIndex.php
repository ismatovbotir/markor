<?php

namespace App\Http\Livewire\Printing;

use App\Domain\Printing\Models\Printer;
use App\Domain\Printing\Services\PrinterService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PrinterIndex extends Component
{
    use WithPagination;

    public bool $showForm      = false;
    public ?int $editingId     = null;
    public string $name        = '';
    public string $type        = 'pdf_browser';
    public string $connection_mode = 'browser';
    public string $host        = '';
    public ?int $port          = 9100;
    public bool $is_active     = true;

    protected function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'type'            => 'required|in:zpl_zebra,ezpl_godex,sbpl_bixolon,pdf_browser',
            'connection_mode' => 'required|in:network,browser',
            'host'            => 'nullable|string|max:255',
            'port'            => 'nullable|integer|min:1|max:65535',
            'is_active'       => 'boolean',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'host', 'editingId']);
        $this->type            = 'pdf_browser';
        $this->connection_mode = 'browser';
        $this->port            = 9100;
        $this->is_active       = true;
        $this->showForm        = true;
    }

    public function openEdit(int $id): void
    {
        $p = Printer::findOrFail($id);
        $this->fill($p->only(['name', 'type', 'connection_mode', 'host', 'port', 'is_active']));
        $this->editingId = $id;
        $this->showForm  = true;
    }

    public function save(PrinterService $service): void
    {
        $this->authorize($this->editingId ? 'update' : 'create', Printer::class);
        $data = $this->validate();

        if ($this->editingId) {
            $service->update(Printer::findOrFail($this->editingId), $data);
        } else {
            $service->store($data);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function delete(int $id, PrinterService $service): void
    {
        $p = Printer::findOrFail($id);
        $this->authorize('delete', $p);
        $service->destroy($p);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(PrinterService $service)
    {
        return view('livewire.printing.printer-index', [
            'printers' => $service->paginate(),
        ]);
    }
}
