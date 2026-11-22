<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\BatchAdminService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: every company's batches — cancel a charge, delete a whole batch (CLAUDE.md §15). */
#[Layout('layouts.app')]
class BatchIndex extends Component
{
    use WithPagination;

    public const FILTERS = ['all', 'charged', 'locked', 'cancelled', 'printed'];

    #[Url(except: '')] public string $search = '';
    #[Url(except: 'all')] public string $filter = 'all';
    #[Url(except: '')] public string $origin = '';
    #[Url(except: '')] public string $company = '';

    // the open action dialog: 'cancel' or 'delete'
    public ?int $actingId = null;
    public string $action = '';
    public string $reason = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'filter', 'origin', 'company'], true)) {
            $this->resetPage();
        }
    }

    public function open(int $id, string $action): void
    {
        abort_unless(in_array($action, ['cancel', 'delete'], true), 422);
        $this->resetValidation();
        $this->actingId = $id;
        $this->action = $action;
        $this->reason = '';
    }

    public function close(): void
    {
        $this->reset(['actingId', 'action', 'reason']);
    }

    public function confirm(BatchAdminService $service): void
    {
        $this->validate(['reason' => 'required|string|min:3|max:500']);
        $batch = ImportBatch::withoutGlobalScope('owner')->findOrFail($this->actingId);
        if (BatchAdminService::checkRunning($batch)) {
            $this->addError('reason', __('batches.check_running'));
            return;
        }
        if ($this->action === 'cancel') {
            if (!BatchAdminService::canCancelCharge($batch)) {
                $this->addError('reason', __('batches.nothing_to_cancel'));
                return;
            }
            $service->cancelCharge($batch, auth()->user(), trim($this->reason));
            $message = __('batches.cancelled_done');
        } else {
            if (!BatchAdminService::canDelete($batch)) {
                $this->addError('reason', __('batches.cancel_first'));
                return;
            }
            $service->delete($batch, auth()->user(), trim($this->reason));
            $message = __('batches.deleted_done');
        }
        $this->close();
        $this->dispatch('notify', type: 'success', message: $message);
    }

    private function query()
    {
        $q = trim($this->search);

        return ImportBatch::withoutGlobalScope('owner')->with(['company:id,name,tin', 'product:id,name_uz,gtin'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('original_filename', 'like', "%$q%")
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$q%")->orWhere('tin', 'like', "%$q%"))))
            ->when($this->origin !== '', fn ($w) => $w->where('origin', $this->origin))
            ->when($this->company !== '', fn ($w) => $w->where('company_id', (int) $this->company));
    }

    private function applyFilter($query, string $filter)
    {
        return match ($filter) {
            'charged'   => $query->whereNotNull('charged_at')->whereNull('charge_cancelled_at'),   // CSV charge still standing
            'locked'    => $query->where('is_locked', true),
            'cancelled' => $query->whereNotNull('charge_cancelled_at'),
            'printed'   => $query->where(fn ($w) => $w->whereHas('labelPdfs')
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('marking_codes')->whereColumn('marking_codes.import_batch_id', 'import_batches.id')->where('marking_codes.status', 'printed'))),
            default     => $query,
        };
    }

    public function render()
    {
        $filter = in_array($this->filter, self::FILTERS, true) ? $this->filter : 'all';
        $batches = $this->applyFilter($this->query(), $filter)->latest('id')->paginate(25);
        $counts = collect(self::FILTERS)->mapWithKeys(fn ($f) => [$f => $this->applyFilter($this->query(), $f)->count()]);
        $acting = $this->actingId ? ImportBatch::withoutGlobalScope('owner')->with('company')->find($this->actingId) : null;

        return view('livewire.admin.batch-index', [
            'batches'   => $batches,
            'counts'    => $counts,
            'activeFilter' => $filter,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'acting'    => $acting,
            'actingPrinted' => $acting ? BatchAdminService::isPrinted($acting) : false,
            'actingRefundable' => $acting ? BatchAdminService::refundable($acting) : 0,
        ]);
    }
}
