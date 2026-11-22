<?php

namespace App\Domain\Printing\Services;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Telegram\TelegramApi;
use App\Jobs\ArchiveLabelPdf;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generate-once storage for batch label PDFs. Order of preference when a PDF is needed:
 *   stored & fresh → local copy, or the Telegram archive (file_id) → otherwise render, store, archive.
 */
class LabelPdfStore
{
    private const DISK = 'local';
    private const TELEGRAM_DOWNLOAD_LIMIT = 20 * 1024 * 1024; // Bot API getFile limit

    public function __construct(private LabelPdfService $renderer, private TelegramApi $telegram) {}

    /**
     * The parts a batch is split into for this design: [['part' => 1, 'first' => 1, 'last' => 2000, 'labels' => 2000, 'ready' => bool], ...]
     */
    public function parts(ImportBatch $batch, LabelTemplate $template): array
    {
        $total = MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->count();
        $size = $template->partSize();
        $count = max(1, (int) ceil($total / $size));
        $stored = LabelPdf::with('template')->where('import_batch_id', $batch->id)->where('label_template_id', $template->id)->get()->keyBy('part');

        return collect(range(1, $count))->map(function ($part) use ($total, $size, $count, $stored) {
            $first = ($part - 1) * $size + 1;
            $pdf = $stored[$part] ?? null;

            return [
                'part' => $part, 'parts' => $count, 'first' => $first, 'last' => min($total, $part * $size),
                'labels' => min($size, $total - $first + 1),
                'ready' => $pdf && !$pdf->isStale() && $pdf->parts === $count && ($pdf->telegram_file_id || $pdf->local_path),
            ];
        })->all();
    }

    /** @return array{0: LabelPdf, 1: string} the record and the PDF contents of one part */
    public function get(ImportBatch $batch, LabelTemplate $template, ?User $user, int $part = 1): array
    {
        $parts = $this->parts($batch, $template);
        $part = max(1, min($part, count($parts)));
        $pdf = LabelPdf::with('template')->where('import_batch_id', $batch->id)->where('label_template_id', $template->id)->where('part', $part)->first();

        if ($pdf && !$pdf->isStale() && $pdf->parts === count($parts) && ($contents = $this->contents($pdf)) !== null) {
            return [$pdf, $contents];
        }

        return $this->render($batch, $template, $user, $pdf, $parts[$part - 1]);
    }

    public function contents(LabelPdf $pdf): ?string
    {
        if ($pdf->local_path && Storage::disk(self::DISK)->exists($pdf->local_path)) {
            return Storage::disk(self::DISK)->get($pdf->local_path);
        }
        if ($pdf->telegram_file_id && $pdf->size_bytes <= self::TELEGRAM_DOWNLOAD_LIMIT) {
            return $this->telegram->download($pdf->telegram_file_id);
        }

        return null;
    }

    /** Codes of one part (same split as the PDF parts), with their products. */
    public function partCodes(ImportBatch $batch, array $part)
    {
        return MarkingCode::withoutGlobalScope('owner')->with('product')->where('import_batch_id', $batch->id)
            ->orderBy('id')->offset($part['first'] - 1)->limit($part['labels'])->get();
    }

    /** PDF generated or sent to the printer: imported / assigned codes become "printed". */
    public function markPrinted($codes): void
    {
        MarkingCode::withoutGlobalScope('owner')->whereIn('id', collect($codes)->pluck('id'))
            ->whereIn('status', ['imported', 'assigned'])
            ->update(['status' => 'printed', 'printed_at' => now()]);
    }

    public function markDownloaded(LabelPdf $pdf): void
    {
        $pdf->increment('downloads', 1, ['last_downloaded_at' => now()]);
    }

    /** @return array{0: LabelPdf, 1: string} */
    private function render(ImportBatch $batch, LabelTemplate $template, ?User $user, ?LabelPdf $existing, array $part): array
    {
        $codes = $this->partCodes($batch, $part);
        $contents = $this->renderer->pdf($template, $codes, pathinfo($batch->original_filename, PATHINFO_FILENAME));
        $this->markPrinted($codes);

        $path = 'pdfs/' . $batch->id . '-' . $template->id . '-p' . $part['part'] . '-' . Str::random(8) . '.pdf';
        Storage::disk(self::DISK)->put($path, $contents);
        if ($existing?->local_path && $existing->local_path !== $path) {
            Storage::disk(self::DISK)->delete($existing->local_path);
        }

        $pdf = LabelPdf::updateOrCreate(
            ['import_batch_id' => $batch->id, 'label_template_id' => $template->id, 'part' => $part['part']],
            [
                'parts' => $part['parts'], 'first_label' => $part['first'],
                'company_id' => $batch->company_id, 'created_by' => $user?->id ?? $batch->uploaded_by,
                'labels' => $codes->count(), 'size_bytes' => strlen($contents), 'local_path' => $path,
                'telegram_file_id' => null, 'telegram_file_unique_id' => null, 'telegram_chat_id' => null, 'archived_at' => null,
            ]
        );
        $pdf->touch();

        if ($this->archiveChat()) {
            ArchiveLabelPdf::dispatch($pdf->id);
        }

        return [$pdf->fresh('template'), $contents];
    }

    /** Where PDFs are archived: the chat set in Sozlamalar, otherwise the super admin's linked Telegram. */
    public function archiveChat(): ?string
    {
        if (!$this->telegram->enabled()) {
            return null;
        }

        return app(\App\Domain\Telegram\Notifier::class)->archiveChat();
    }
}
