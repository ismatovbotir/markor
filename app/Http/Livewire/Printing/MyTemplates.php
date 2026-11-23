<?php

namespace App\Http\Livewire\Printing;

use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Domain\Printing\Services\LabelPdfService;
use App\Domain\Printing\Services\LabelTemplateService;
use Livewire\Attributes\Layout;

/**
 * A client designs its company's own label templates in the same visual editor as the super admin.
 * They are private to the company; the super admin may make one global only with the consent given here.
 */
#[Layout('layouts.app')]
class MyTemplates extends LabelTemplateIndex
{
    public function mount(): void
    {
        $this->authorize('create', LabelTemplate::class);

        // links from the template catalog: "Copy and change", "Edit", "New design"
        if ($copy = request()->integer('copy')) {
            $this->openCopy($copy);
        } elseif ($edit = request()->integer('edit')) {
            $this->openEdit($edit);
        } elseif (request()->boolean('new')) {
            $this->openCopy();
        }
    }

    /** New design: only while the company has free slots; the grey markor.uz mark starts switched on. */
    public function openCopy(?int $id = null): void
    {
        $company = auth()->user()->company;
        if (LabelTemplateService::slotsLeft($company) < 1) {
            $this->dispatch('notify', type: 'error', message: __('templates.limit_reached', ['limit' => $company->template_limit]));
            return;
        }
        parent::openCopy($id);
        $this->editor['watermark'] = true;
    }

    public function render(LabelTemplateService $service, LabelPdfService $pdf, DataMatrixRenderer $renderer)
    {
        $sample = $this->showForm ? $pdf->demoCode() : null;

        return view('livewire.printing.my-templates', [
            'templates' => $service->paginateOwn(auth()->user()->company_id),
            'slotsLeft' => LabelTemplateService::slotsLeft(auth()->user()->company),
            'limit'     => (int) auth()->user()->company->template_limit,
            'sampleDm'  => $sample ? $renderer->renderAsBase64($sample, 4) : null,
            'sample'    => $sample ? LayoutRenderer::markingRow($sample, 'partiya_01') : null,
        ]);
    }
}
