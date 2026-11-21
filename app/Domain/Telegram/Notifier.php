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

    /** New sign-up → super admin. */
    public function userRegistered(User $user): void
    {
        $this->toAdmin('bot.admin_new_user', [
            'name' => e($user->name), 'email' => e($user->email), 'phone' => e($user->phone ?: '—'),
            'locale' => strtoupper((string) $user->locale), 'url' => route('admin.clients'),
        ]);
    }

    /** A new user filled in the company (tax number) → super admin. */
    public function companyRegistered(\App\Domain\Companies\Company $company, User $user): void
    {
        $this->toAdmin('bot.admin_new_company', [
            'company' => e($company->name), 'tin' => e($company->tin), 'director' => e($company->director ?: '—'),
            'phone' => e($company->phone ?: $user->phone ?: '—'), 'user' => e($user->name),
            'free' => $company->free_until?->format('d.m.Y') ?? '—', 'source' => $company->source === 'lookup' ? '✓' : '✎',
            'url' => route('admin.companies.show', $company),
        ]);
    }

    /** A client ordered a new label template → super admin. */
    public function templateRequested(\App\Domain\Printing\Models\TemplateRequest $request): void
    {
        $this->toAdmin('bot.admin_template_request', [
            'company' => e($request->company?->name ?? ''), 'user' => e($request->user?->name ?? ''), 'phone' => e($request->user?->phone ?: '—'),
            'size' => e($request->size()), 'gap' => $request->gap_mm !== null ? rtrim(rtrim(number_format($request->gap_mm, 1, '.', ''), '0'), '.') . ' mm' : '—',
            'comment' => e($request->comment ?: '—'), 'url' => route('admin.template-requests'),
        ]);
    }

    /** The super admin answered a template request → the client company. */
    public function templateRequestAnswered(\App\Domain\Printing\Models\TemplateRequest $request): void
    {
        $this->toCompany($request->company_id, 'bot.template_request_' . $request->status, fn () => [
            'size' => e($request->size()), 'note' => e($request->admin_note ?: '—'), 'url' => route('templates.catalog'),
        ]);
    }

    /** An alert to the super admin's chat, in the super admin's language; never throws. */
    private function toAdmin(string $key, array $replace): void
    {
        try {
            if ($chat = $this->adminChat()) {
                $locale = User::where('role', 'admin')->orderBy('id')->value('locale') ?: config('app.locale');
                $this->api->sendMessage($chat, __($key, $replace, $locale));
            }
        } catch (\Throwable $e) {
            report($e); // an alert must never break registration
        }
    }

    /** System alerts: TELEGRAM_ADMIN_CHAT_ID, otherwise the super admin's linked Telegram. */
    public function adminChat(): ?string
    {
        if (!$this->api->enabled()) {
            return null;
        }

        return config('telegram.admin_chat')
            ?: User::where('role', 'admin')->whereNotNull('telegram_chat_id')->orderBy('id')->value('telegram_chat_id');
    }

    /** PDF archive: TELEGRAM_ARCHIVE_CHAT_ID, otherwise the admin chat. */
    public function archiveChat(): ?string
    {
        if (!$this->api->enabled()) {
            return null;
        }

        return config('telegram.archive_chat') ?: $this->adminChat();
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
