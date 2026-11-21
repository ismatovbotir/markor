<?php

namespace App\Jobs;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Services\LabelPdfStore;
use App\Domain\Telegram\BotHandler;
use App\Domain\Telegram\TelegramApi;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/** Sends a batch's labels PDF to the client in Telegram — from the admin archive (file_id) if it exists. */
class SendBatchPdfToTelegram implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public int $batchId, public int $templateId, public string $chatId) {}

    public function handle(LabelPdfStore $store, TelegramApi $api, BotHandler $bot): void
    {
        $batch = ImportBatch::withoutGlobalScope('owner')->findOrFail($this->batchId);
        $template = LabelTemplate::findOrFail($this->templateId);
        $user = User::where('telegram_chat_id', $this->chatId)->first();
        $caption = fn ($pdf) => $bot->t($user, 'bot.pdf_ready', ['count' => $pdf->labels, 'template' => e($template->name)])
            . ($pdf->parts > 1 ? "\n" . $bot->t($user, 'bot.pdf_part', ['part' => $pdf->part, 'parts' => $pdf->parts, 'first' => $pdf->first_label, 'last' => $pdf->lastLabel()]) : '');

        foreach ($store->parts($batch, $template) as $info) {
            $pdf = \App\Domain\Printing\Models\LabelPdf::with('template')->where('import_batch_id', $batch->id)
                ->where('label_template_id', $template->id)->where('part', $info['part'])->first();

            // already archived and still valid: instant re-send, no rendering, no upload
            if ($info['ready'] && $pdf?->telegram_file_id
                && $api->call('sendDocument', ['chat_id' => $this->chatId, 'document' => $pdf->telegram_file_id, 'caption' => $caption($pdf), 'parse_mode' => 'HTML']) !== null) {
                $store->markDownloaded($pdf);
                continue;
            }

            [$pdf, $contents] = $store->get($batch, $template, $user, $info['part']);
            $file = Str::slug(pathinfo($batch->original_filename, PATHINFO_FILENAME), '_') . ($pdf->parts > 1 ? "_part{$pdf->part}of{$pdf->parts}" : '') . '.pdf';
            if ($api->sendDocument($this->chatId, $contents, $file, $caption($pdf))) {
                $store->markDownloaded($pdf);
            } else {
                $api->sendMessage($this->chatId, $bot->t($user, 'bot.pdf_failed'));
                return;
            }
        }
    }
}
