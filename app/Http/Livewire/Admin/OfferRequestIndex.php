<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Offers\Models\OfferRequest;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: order requests for "Biz taklif qilamiz" items. */
#[Layout('layouts.app')]
class OfferRequestIndex extends Component
{
    use WithPagination;

    public string $status = 'new';

    public function updatingStatus(): void { $this->resetPage(); }

    public function toggleDone(int $id): void
    {
        $request = OfferRequest::findOrFail($id);
        $request->update(['status' => $request->status === 'done' ? 'new' : 'done']);
    }

    public function render()
    {
        return view('livewire.admin.offer-request-index', [
            'requests' => OfferRequest::with('user:id,name,email')
                ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
                ->latest()->paginate(30),
            'newCount' => OfferRequest::where('status', 'new')->count(),
        ]);
    }
}
