<?php

namespace App\Http\Livewire\Marking;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Support\MarkingSystem;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Prototype: reads the DataMatrix codes from a client's existing PDF in the browser (the PDF is
 * never uploaded) and gives back a CSV for the normal import. Only the category structures are
 * sent to the page, to recognise which strings are marking codes.
 */
#[Layout('layouts.app')]
class PdfCodeReader extends Component
{
    public function mount(): void
    {
        $this->authorize('create', ImportBatch::class);
    }

    public function render()
    {
        $name = 'name_' . (app()->getLocale() === 'ru' ? 'ru' : 'uz');

        return view('livewire.marking.pdf-code-reader', [
            'groups' => ProductGroup::active()->whereNotNull('structure')
                ->orderBy('sort_order')->orderBy('id')
                ->get()
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->{$name} ?: $g->name_uz, 'system' => $g->marking_system ?: MarkingSystem::ASLBELGISI, 'structure' => $g->structure])
                ->values(),
            'systems'  => MarkingSystem::options(),
            'fallback' => auth()->user()->company?->marking_system ?: MarkingSystem::ASLBELGISI,
        ]);
    }
}
