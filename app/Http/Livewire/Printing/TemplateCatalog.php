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
    public const SIZE_TOLERANCE_MM = 5; // the size filter also shows templates up to ±5 mm away
    #[\Livewire\Attributes\Url(as: 'w', except: '')] public $width = '';    // label width filter, mm ('' = any)
    #[\Livewire\Attributes\Url(as: 'h', except: '')] public $height = '';   // label height filter, mm ('' = any)
    #[\Livewire\Attributes\Url(as: 'mine', except: false)] public bool $mine = false; // only "my templates"
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

    /** Add a template to the company's "my templates" or remove it (shared by the whole company). */
    public function toggleMine(int $id): void
    {
        $company = auth()->user()->company;
        abort_unless($company, 403);
        $template = LabelTemplate::findOrFail($id);
        $company->labelTemplates()->toggle([$template->id]);
    }

    public function clearSize(): void
    {
        $this->width = '';
        $this->height = '';
    }

    public function render(LabelPdfService $pdf)
    {
        $company = auth()->user()->company;
        $mineIds = $company ? $company->labelTemplates()->pluck('label_templates.id')->all() : [];
        $total = LabelTemplate::count();
        $w = is_numeric($this->width) ? (int) $this->width : null;
        $h = is_numeric($this->height) ? (int) $this->height : null;
        $tol = self::SIZE_TOLERANCE_MM;

        $templates = LabelTemplate::orderByDesc('is_default')->orderBy('name')
            ->when($w !== null, fn ($q) => $q->whereBetween('width_mm', [$w - $tol, $w + $tol]))
            ->when($h !== null, fn ($q) => $q->whereBetween('height_mm', [$h - $tol, $h + $tol]))
            ->when($this->mine, fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->get();
        if ($w !== null || $h !== null) {
            // closest size first (exact match on top)
            $templates = $templates->sortBy(fn ($t) => abs($t->width_mm - ($w ?? $t->width_mm)) + abs($t->height_mm - ($h ?? $t->height_mm)))->values();
        }

        return view('livewire.printing.template-catalog', [
            'templates' => $templates,
            'previews'  => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)]),
            'mineIds'   => $mineIds,
            'total'     => $total,
            'filtering' => $w !== null || $h !== null || $this->mine,
            'requests'  => auth()->user()->company_id
                ? TemplateRequest::where('company_id', auth()->user()->company_id)->latest()->limit(10)->get()
                : collect(),
        ]);
    }
}
