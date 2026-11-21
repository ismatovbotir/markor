<?php

namespace App\Domain\Telegram;

use App\Domain\Billing\BalanceService;
use App\Domain\Billing\Billing;
use App\Domain\Billing\Payment;
use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;

/** Telegram messages about money: to the company's linked users and to the super admin. */
class Notifier
{
    public function __construct(private TelegramApi $api) {}

    public function batchLocked(ImportBatch $batch): void
    {
        $company = $batch->company;
        $this->toCompany($batch->company_id, 'bot.batch_locked', fn () => [
            'file' => e($batch->original_filename), 'amount' => Billing::money($batch->amount),
            'balance' => Billing::money($company?->balance), 'need' => Billing::money(BalanceService::shortfall($company)),
            'url' => route('balance'),
        ]);
    }

    public function batchUnlocked(ImportBatch $batch): void
    {
        $this->toCompany($batch->company_id, 'bot.batch_unlocked', fn () => ['file' => e($batch->original_filename)]);
        app(BotHandler::class)->notifyBatchFinished($batch->fresh()); // PDF buttons to the uploader
    }

    public function paymentConfirmed(Payment $payment): void
    {
        $this->toCompany($payment->company_id, 'bot.payment_confirmed', fn () => [
            'amount' => Billing::money($payment->amount), 'balance' => Billing::money($payment->company->fresh()->balance),
        ]);
    }

    public function freePeriodChanged(\App\Domain\Companies\Company $company): void
    {
        $this->toCompany($company->id, $company->free_until ? 'bot.free_period_extended' : 'bot.free_period_ended', fn () => [
            'date' => $company->free_until?->format('d.m.Y'), 'paid_from' => Billing::paidFrom($company)?->format('d.m.Y'),
        ]);
    }

    public function paymentRejected(Payment $payment): void
    {
        $this->toCompany($payment->company_id, 'bot.payment_rejected', fn () => [
            'amount' => Billing::money($payment->amount), 'note' => e($payment->admin_note ?: '—'),
        ]);
    }

    /** New "I paid" report → the super admin. */
    public function paymentReported(Payment $payment): void
    {
        if (!$chat = $this->adminChat()) {
            return;
        }
        $this->api->sendMessage($chat, sprintf(
            "💳 <b>%s</b>\n%s · %s\n%s\n%s",
            e($payment->company->name), Billing::money($payment->amount), e($payment->user?->name ?? ''),
            e($payment->comment ?? ''), route('admin.payments')
        ));
    }

    /** The super admin's chat: the archive chat from Sozlamalar, otherwise the super admin's linked Telegram. */
    public function adminChat(): ?string
    {
        if (!$this->api->enabled()) {
            return null;
        }

        return config('telegram.archive_chat')
            ?: User::where('role', 'admin')->whereNotNull('telegram_chat_id')->orderBy('id')->value('telegram_chat_id');
    }

    private function toCompany(?int $companyId, string $key, \Closure $replace): void
    {
        if (!$companyId || !$this->api->enabled()) {
            return;
        }
        $values = $replace();
        User::where('company_id', $companyId)->whereNotNull('telegram_chat_id')->where('is_active', true)->get()
            ->each(fn (User $u) => $this->api->sendMessage($u->telegram_chat_id, __($key, $values, $u->locale ?: config('app.locale'))));
    }
}
