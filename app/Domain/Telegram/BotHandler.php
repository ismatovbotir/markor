<?php

namespace App\Domain\Telegram;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Marking\Services\ImportBatchService;
use App\Domain\Printing\Models\LabelTemplate;
use App\Jobs\SendBatchPdfToTelegram;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Handles one Telegram update.
 *   /start <token>  → ask to share the phone; phone must match the cabinet → the cabinet shows a
 *                     6-digit code → the client types it here → linked.
 *   linked users    → send a CSV (queued like a web upload), /status, PDF buttons, /help.
 * Messages use the language set in the client's cabinet.
 */
class BotHandler
{
    public function __construct(private TelegramApi $api, private ImportBatchService $batches) {}

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->callback($update['callback_query']);
            return;
        }

        $message = $update['message'] ?? null;
        if (!$message || ($message['chat']['type'] ?? '') !== 'private') {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim($message['text'] ?? '');
        $user = User::where('telegram_chat_id', $chatId)->where('is_active', true)->first();
        $link = TelegramLink::where('chat_id', $chatId)->whereIn('status', ['started', 'phone_ok'])->latest('id')->first();

        if (str_starts_with($text, '/start')) {
            $this->start($chatId, trim(Str::after($text, '/start')), $user, $message['from'] ?? []);
            return;
        }
        if (isset($message['contact'])) {
            $this->contact($chatId, $message, $link);
            return;
        }
        if ($link && $link->status === 'phone_ok' && preg_match('/^\d{6}$/', $text)) {
            $this->code($chatId, $text, $link);
            return;
        }
        if (!$user) {
            $this->say($chatId, null, 'bot.not_linked');
            return;
        }

        if (isset($message['document'])) {
            $this->document($chatId, $message['document'], $user);
        } elseif ($text === '/status') {
            $this->status($chatId, $user);
        } else {
            $this->say($chatId, $user, 'bot.instructions', ['name' => e($user->name)]);
        }
    }

    // ---------- linking ----------

    private function start(string $chatId, string $token, ?User $user, array $from = []): void
    {
        if ($token === '') {
            $user ? $this->say($chatId, $user, 'bot.instructions', ['name' => e($user->name)]) : $this->say($chatId, null, 'bot.not_linked');
            return;
        }

        $link = TelegramLink::with('user')->where('token', $token)->first();
        if (!$link || !$link->isOpen()) {
            $this->say($chatId, $link?->user, 'bot.link_expired');
            return;
        }

        $link->update([
            'chat_id' => $chatId, 'status' => 'started',
            'tg_username' => $from['username'] ?? null,
            'tg_name' => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: null,
        ]);
        $this->api->sendMessage($chatId, $this->t($link->user, 'bot.share_phone'), [
            'keyboard'          => [[['text' => $this->t($link->user, 'bot.share_phone_button'), 'request_contact' => true]]],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ]);
    }

    private function contact(string $chatId, array $message, ?TelegramLink $link): void
    {
        if (!$link || !$link->isOpen()) {
            $this->say($chatId, $link?->user, 'bot.link_expired', [], removeKeyboard: true);
            return;
        }

        // only the sender's own contact counts
        if (($message['contact']['user_id'] ?? null) !== ($message['from']['id'] ?? false)) {
            $this->say($chatId, $link->user, 'bot.own_phone_only');
            return;
        }

        $user = $link->user;
        $link->update(['shared_phone' => '+' . ltrim((string) ($message['contact']['phone_number'] ?? ''), '+')]);
        $phones = [$user->phone, $user->company?->phone];
        if (!collect($phones)->contains(fn ($p) => TelegramLink::samePhone($p, $message['contact']['phone_number'] ?? ''))) {
            $link->update(['status' => 'phone_mismatch']);
            $this->say($chatId, $user, 'bot.phone_mismatch', [], removeKeyboard: true);
            return;
        }

        $link->update(['status' => 'phone_ok', 'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT), 'attempts' => 0]);
        $this->say($chatId, $user, 'bot.enter_code', [], removeKeyboard: true);
    }

    private function code(string $chatId, string $code, TelegramLink $link): void
    {
        if (!$link->isOpen()) {
            $this->say($chatId, $link->user, 'bot.link_expired');
            return;
        }

        if (!hash_equals((string) $link->code, $code)) {
            $link->increment('attempts');
            if ($link->attempts >= 5) {
                $link->update(['status' => 'failed']);
                $this->say($chatId, $link->user, 'bot.too_many_attempts');
            } else {
                $this->say($chatId, $link->user, 'bot.wrong_code');
            }
            return;
        }

        // one chat per account and one account per chat
        User::where('telegram_chat_id', $chatId)->where('id', '!=', $link->user_id)->update(['telegram_chat_id' => null]);
        $link->user->update(['telegram_chat_id' => $chatId]);
        $link->update(['status' => 'linked', 'code' => null]);

        $this->say($chatId, $link->user, 'bot.linked', ['name' => e($link->user->name)]);
        $this->say($chatId, $link->user, 'bot.instructions', ['name' => e($link->user->name)]);
    }

    // ---------- working with batches ----------

    private function document(string $chatId, array $document, User $user): void
    {
        $name = (string) ($document['file_name'] ?? 'codes.csv');
        if (!preg_match('/\.(csv|txt)$/i', $name)) {
            $this->say($chatId, $user, 'bot.csv_only');
            return;
        }
        if (($document['file_size'] ?? 0) > config('uploads.codes.max_kb') * 1024) {
            $this->say($chatId, $user, 'bot.file_too_big', ['mb' => round(config('uploads.codes.max_kb') / 1024, 1)]);
            return;
        }
        if (!$user->company_id) {
            $this->say($chatId, $user, 'bot.company_first', ['url' => route('company.setup')]);
            return;
        }
        if (!$user->company?->is_active) {
            $this->say($chatId, $user, 'bot.company_blocked');
            return;
        }

        if ($duplicate = ImportBatch::loadedWithName($user->company_id, $name)) {
            $this->say($chatId, $user, 'bot.duplicate_file', ['file' => e($duplicate->original_filename), 'date' => $duplicate->created_at->format('d.m.Y H:i'), 'id' => $duplicate->id]);
            return;
        }

        $contents = $this->api->download($document['file_id']);
        if ($contents === null) {
            $this->say($chatId, $user, 'bot.download_failed');
            return;
        }
        if (!\App\Rules\PlainTextFile::isText(substr($contents, 0, 8192))) {
            $this->say($chatId, $user, 'bot.not_text');
            return;
        }

        $batch = $this->batches->createFromContents($user->id, $name, $contents);
        $this->say($chatId, $user, 'bot.queued', ['file' => e($name), 'id' => $batch->id]);
    }

    private function status(string $chatId, User $user): void
    {
        $batches = ImportBatch::withoutGlobalScope('owner')->where('company_id', $user->company_id)->latest('id')->limit(5)->get();
        if ($batches->isEmpty()) {
            $this->say($chatId, $user, 'bot.no_batches');
            return;
        }

        $lines = $batches->map(fn ($b) => '• <b>' . e($b->original_filename) . '</b> — ' . $this->t($user, 'import.status.' . $b->status)
            . ($b->status === 'completed' ? ', ' . $b->processed_rows . ' ' . $this->t($user, 'billing.codes') : ''));
        $this->api->sendMessage($chatId, $this->t($user, 'bot.status_title') . "\n" . $lines->implode("\n"));
    }

    private function callback(array $query): void
    {
        $chatId = (string) ($query['message']['chat']['id'] ?? '');
        $user = User::where('telegram_chat_id', $chatId)->where('is_active', true)->first();
        $this->api->call('answerCallbackQuery', ['callback_query_id' => $query['id']]);

        if (!$user || !preg_match('/^pdf:(\d+):(\d+)$/', $query['data'] ?? '', $m)) {
            return;
        }
        if (!$user->company?->is_active) {
            $this->say($chatId, $user, 'bot.company_blocked'); // a blocked company gets no PDFs
            return;
        }

        $batch = ImportBatch::withoutGlobalScope('owner')->where('company_id', $user->company_id)->find($m[1]);
        $template = LabelTemplate::find($m[2]);
        if (!$batch || !$template || $batch->status !== 'completed') {
            return;
        }
        if ($batch->is_locked) {
            $this->say($chatId, $user, 'bot.batch_still_locked', ['url' => route('balance')]);
            return;
        }

        $this->say($chatId, $user, 'bot.pdf_started', ['template' => e($template->name)]);
        SendBatchPdfToTelegram::dispatch($batch->id, $template->id, $chatId);
    }

    // ---------- notifications (called from the batch processor) ----------

    /** Sends the result of a loaded batch to its owner, with a button per label design. */
    public function notifyBatchFinished(ImportBatch $batch): void
    {
        $user = User::find($batch->uploaded_by);
        if (!$user?->telegram_chat_id || !$this->api->enabled()) {
            return;
        }

        if ($batch->is_locked) {
            return; // the "top up your balance" message was sent when it was locked
        }
        if ($batch->status === 'failed' || $batch->processed_rows === 0) {
            $reason = collect($batch->error_log ?? [])->take(3)->map(fn ($e) => '• ' . e($e))->implode("\n");
            $this->api->sendMessage($user->telegram_chat_id, $this->t($user, 'bot.batch_failed', ['file' => e($batch->original_filename)]) . ($reason ? "\n" . $reason : ''));
            return;
        }

        $templates = LabelTemplate::orderByDesc('is_default')->orderBy('name')->limit(8)->get();
        $buttons = $templates->map(fn ($t) => [['text' => '📄 ' . $t->name . " ({$t->width_mm}×{$t->height_mm})", 'callback_data' => "pdf:{$batch->id}:{$t->id}"]])->values()->all();

        $text = $this->t($user, 'bot.batch_done', ['file' => e($batch->original_filename), 'count' => $batch->processed_rows]);
        $money = fn ($v) => \App\Domain\Billing\Billing::money($v);
        $text .= "\n" . $this->t($user, 'bot.batch_cost', [
            'count' => $batch->processed_rows, 'price' => $money($batch->unit_price),
            'gross' => $money($batch->processed_rows * $batch->unit_price), 'amount' => $money($batch->amount),
        ]);
        if ($batch->discount_percent > 0) {
            $until = \App\Domain\Billing\Billing::discountUntil($batch->company);
            $text .= "\n" . $this->t($user, $until ? 'bot.batch_discount_until' : 'bot.batch_discount', [
                'percent' => \App\Domain\Billing\Billing::percent($batch->discount_percent), 'date' => $until?->format('d.m.Y'),
            ]);
        }
        if ($batch->failed_rows > 0) {
            $text .= "\n" . $this->t($user, 'bot.batch_skipped', ['count' => $batch->failed_rows]);
        }
        $this->api->sendMessage($user->telegram_chat_id, $text . "\n\n" . $this->t($user, 'bot.choose_design'), ['inline_keyboard' => $buttons]);

        // products of this batch the classifier did not know: ask the client to name them
        $unnamed = \App\Domain\Catalog\Models\Product::withoutGlobalScope('owner')
            ->whereIn('id', \App\Domain\Marking\Models\MarkingCode::withoutGlobalScope('owner')->where('import_batch_id', $batch->id)->distinct()->pluck('product_id'))
            ->get()->map(fn ($p) => $p->forCompany($batch->company_id))->filter(fn ($p) => $p->needsName());
        if ($unnamed->isNotEmpty()) {
            $this->api->sendMessage($user->telegram_chat_id, $this->t($user, 'bot.items_need_name', [
                'file'  => e($batch->original_filename),
                'gtins' => $unnamed->map(fn ($p) => '• <code>' . $p->gtin . '</code>')->implode("\n"),
                'url'   => route('import-batches.index'),
            ]));
        }
    }

    // ---------- helpers ----------

    private function say(string $chatId, ?User $user, string $key, array $replace = [], bool $removeKeyboard = false): void
    {
        $this->api->sendMessage($chatId, $this->t($user, $key, $replace), $removeKeyboard ? ['remove_keyboard' => true] : null);
    }

    /** Translation in the user's cabinet language. */
    public function t(?User $user, string $key, array $replace = []): string
    {
        return __($key, $replace, $user?->locale ?: config('app.locale'));
    }
}
