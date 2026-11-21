<?php

namespace App\Jobs;

use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\PrintJob;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RenderPrintJobJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public PrintJob $job) {}

    public function handle(): void
    {
        try {
            $this->job->update(['status' => 'rendering']);

            $codes = MarkingCode::whereIn('id', $this->job->code_ids)->get();

            if ($this->job->template->printer_type === 'pdf_browser') {
                $this->renderPdf($codes);
            } else {
                $this->renderRaw($codes);
            }

            $this->job->update(['status' => 'ready']);

        } catch (\Throwable $e) {
            $this->job->update(['status' => 'failed']);
            throw $e;
        }
    }

    private function renderPdf($codes): void
    {
        $renderer = new DataMatrixRenderer();
        $html = '<style>body { margin: 0; padding: 0; font-family: Arial, sans-serif; }</style>';

        foreach ($codes as $code) {
            $dm = $renderer->render($code, 10);
            $html .= <<<HTML
<div style="width: {$this->job->template->width_mm}mm; height: {$this->job->template->height_mm}mm;
            page-break-after: avoid; border: 1px solid #ccc; padding: 2mm; box-sizing: border-box;">
    $dm
    <small>{$code->gtin} {$code->serial}</small>
</div>
HTML;
        }

        $pdf = \PDF::loadHTML($html);
        $path = "print-jobs/{$this->job->uuid}.pdf";
        \Storage::put($path, $pdf->output());
        $this->job->update(['output_path' => $path]);
    }

    private function renderRaw($codes): void
    {
        $output = '';
        foreach ($codes as $code) {
            $output .= str_replace(
                ['{datamatrix}', '{gtin}', '{name}', '{sscc}', '{date}'],
                ['[DataMatrix]', $code->gtin, $code->product?->name_ru ?? '—', '—', now()->format('d.m.Y')],
                $this->job->template->template
            ) . "\n";
        }

        $path = "print-jobs/{$this->job->uuid}.txt";
        \Storage::put($path, $output);
        $this->job->update(['output_path' => $path]);
    }
}
