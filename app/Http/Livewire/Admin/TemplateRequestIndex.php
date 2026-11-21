<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Models\TemplateRequest;
use App\Domain\Telegram\Notifier;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: clients' requests for new label templates; answering notifies the client (Telegram). */
#[Layout('layouts.app')]
class TemplateRequestIndex extends Component
{
    use WithPagination;

    public string $status = 'new';
    public ?int $answering = null;
    public string $note = '';
    public ?int $templateId = null;

    public function updatingStatus(): void { $this->resetPage(); }

    public function open(int $id): void
    {
        $r = TemplateRequest::findOrFail($id);
        $this->answering = $r->id;
        $this->note = (string) $r->admin_note;
        $this->templateId = $r->template_id;
    }

    public function answer(string $status, Notifier $notifier): void
    {
        abort_unless(in_array($status, ['done', 'rejected'], true), 422);
        $this->validate(['note' => 'nullable|string|max:1000', 'templateId' => 'nullable|exists:label_templates,id']);
        $r = TemplateRequest::findOrFail($this->answering);
        $r->update(['status' => $status, 'admin_note' => $this->note ?: null, 'template_id' => $status === 'done' ? $this->templateId : null, 'decided_at' => now()]);
        $notifier->templateRequestAnswered($r);
        $this->answering = null;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function render()
    {
        return view('livewire.admin.template-request-index', [
            'requests'  => TemplateRequest::with(['company:id,name,tin', 'user:id,name,phone', 'template:id,name'])
                ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))->latest()->paginate(30),
            'newCount'  => TemplateRequest::where('status', 'new')->count(),
            'templates' => LabelTemplate::orderBy('name')->get(['id', 'name', 'width_mm', 'height_mm']),
        ]);
    }
}
