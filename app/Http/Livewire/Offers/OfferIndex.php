<?php

namespace App\Http\Livewire\Offers;

use App\Domain\Offers\Models\OfferCategory;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class OfferIndex extends Component
{
    public function render(LabelPdfService $pdf)
    {
        $templates = LabelTemplate::public()->orderByDesc('is_default')->orderBy('name')->limit(OfferCategory::SHOWN_ITEMS)->get();

        return view('livewire.offers.offer-index', [
            'catalog'          => OfferCategory::catalog(),
            'templatePreviews' => $templates->mapWithKeys(fn ($t) => [$t->id => $pdf->previewDocument($t)])->all(),
            'phone'            => config('contact.phone'),
        ]);
    }
}
