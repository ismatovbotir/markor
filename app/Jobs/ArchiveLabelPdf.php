<?php

namespace App\Jobs;

use App\Domain\Printing\Models\LabelPdf;
use App\Domain\Printing\Services\LabelPdfStore;
use App\Domain\Telegram\TelegramApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/** Sends a generated PDF to the admin's Telegram archive, keeps the file_id, removes the local copy. */
class ArchiveLabelPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 180;

    public function __construct(public int $pdfId) {}

    public function handle(LabelPdfStore $store, TelegramApi $telegram): void
    {
        $pdf = LabelPdf::with(['batch.company', 'template'])->find($this->pdfId);
        $chat = $store->archiveChat();
        if (!$pdf || !$chat || $pdf->telegram_file_id || !$pdf->local_path || !Storage::disk('local')->exists($pdf->local_path)) {
            return;
        }

        $caption = sprintf("🗂 #%d · %s\n%s · %s\n%d/%d · %d–%d (%d labels)", $pdf->id, $pdf->batch?->company?->name ?? '—',
            $pdf->batch?->original_filename, $pdf->template?->name, $pdf->part, $pdf->parts, $pdf->first_label, $pdf->lastLabel(), $pdf->labels);
        $name = \Illuminate\Support\Str::slug(pathinfo($pdf->batch?->original_filename ?? 'labels', PATHINFO_FILENAME), '_')
            . ($pdf->parts > 1 ? "_part{$pdf->part}of{$pdf->parts}" : '') . '.pdf';

        $sent = $telegram->sendDocument($chat, Storage::disk('local')->get($pdf->local_path), $name, e($caption));
        if (!isset($sent['document']['file_id'])) {
            throw new \RuntimeException("Archiving PDF #{$pdf->id} to Telegram failed");
        }

        Storage::disk('local')->delete($pdf->local_path);
        $pdf->update([
            'telegram_file_id' => $sent['document']['file_id'],
            'telegram_file_unique_id' => $sent['document']['file_unique_id'] ?? null,
            'telegram_chat_id' => (string) $chat,
            'local_path' => null,
            'archived_at' => now(),
        ]);
    }
}
