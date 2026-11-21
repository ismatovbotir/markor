<?php

namespace App\Http\Livewire\Printing;

use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Models\TemplateRequest;
use App\Domain\Printing\Services\LabelPdfService;
use App\Domain\Telegram\Notifier;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Clients don't design labels: they browse the super admin's templates and can order a new one. */
#[Layout('layouts.app')]
class TemplateCatalog extends Component
{
    public bool $ordering = false;
    public array $order = ['width_mm' => '', 'height_mm' => '', 'columns' => 1, 'rows' => '', 'gap_mm' => '', 'comment' => ''];

    public function openOrder(): void
    {
        $this->resetValidation();
        $this->order = ['width_mm' => '', 'height_mm' => '', 'columns' => 1, 'rows' => '', 'gap_mm' => '', 'comment' => ''];
        $this->ordering = true;
    }

    public function sendOrder(Notifier $notifier): void
    {
        abort_unless(auth()->user()->company_id, 403);
        $data = $this->validate([
            'order.width_mm'  => 'required|integer|min:10|max:300',
            'order.height_mm' => 'required|integer|min:10|max:300',
            'order.columns'   => 'required|integer|min:1|max:10',
            'order.rows'      => 'nullable|integer|min:1|max:50',
            'order.gap_mm'    => 'nullable|numeric|min:0|max:30',
            'order.comment'   => 'nullable|string|max:1000',
        ])['order'];

        // no flooding: at most 5 open requests per company
        if (TemplateRequest::where('company_id', auth()->user()->company_id)->where('status', 'new')->count() >= 5) {
            $this->addError('order.comment', __('template_requests.too_many'));
            return;
        }

        $request = TemplateRequest::create([
            'company_id' => auth()->user()->company_id, 'user_id' => auth()->id(),
            'width_mm' => (int) $data['width_mm'], 'height_mm' => (int) $data['height_mm'], 'columns' => (int) $data['columns'],
            'rows' => filled($data['rows']) ? (int) $data['rows'] : null, 'gap_mm' => filled($data['gap_mm']) ? (float) $data['gap_mm'] : null,
            'comment' => filled($data['comment']) ? trim($data['comment']) : null,
        ]);
        $notifier->templateRequested($request->load('company', 'user'));

        $this->ordering = false;
        $this->dispatch('notify', type: 'success', message: __('template_requests.sent'));
    }

    public function render(LabelPdfService $pdf)
    {
        $templates = LabelTemplate::orderByDesc('is_default')->orderBy('name')->get();

        return view('livewire.printing.template-catalog', [
            'templates' => $templates,
            'previews'  => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)]),
            'requests'  => auth()->user()->company_id
                ? TemplateRequest::where('company_id', auth()->user()->company_id)->latest()->limit(10)->get()
                : collect(),
        ]);
    }
}
