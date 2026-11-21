<?php

namespace App\Console\Commands;

use App\Domain\Printing\Models\LabelPdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Local PDF copies that were never archived (no Telegram archive configured) are kept only for a few days. */
class PruneLabelPdfs extends Command
{
    protected $signature = 'pdfs:prune {--days=7}';
    protected $description = 'Delete local copies of label PDFs older than N days (the print log rows stay)';

    public function handle(): int
    {
        $n = 0;
        LabelPdf::whereNotNull('local_path')->where('updated_at', '<', now()->subDays((int) $this->option('days')))
            ->chunkById(200, function ($pdfs) use (&$n) {
                foreach ($pdfs as $pdf) {
                    Storage::disk('local')->delete($pdf->local_path);
                    $pdf->update(['local_path' => null]);
                    $n++;
                }
            });
        $this->info("Pruned $n local PDF copies");

        return self::SUCCESS;
    }
}
