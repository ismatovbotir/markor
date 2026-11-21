<?php

namespace App\Http\Livewire\Printing;

use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Clients don't design labels: they browse the super admin's templates with a preview. */
#[Layout('layouts.app')]
class TemplateCatalog extends Component
{
    public function render(LabelPdfService $pdf)
    {
        $templates = LabelTemplate::orderByDesc('is_default')->orderBy('name')->get();

        return view('livewire.printing.template-catalog', [
            'templates' => $templates,
            'previews'  => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)]),
        ]);
    }
}
