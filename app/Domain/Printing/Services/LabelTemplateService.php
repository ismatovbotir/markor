<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Models\LabelTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LabelTemplateService
{
    /** Super admin: every template, with its owner. $owner: '' all, 'global' made by the admin, 'clients' made by clients, 'deleted' deleted by clients. */
    public function paginate(int $perPage = 20, string $owner = ''): LengthAwarePaginator
    {
        return LabelTemplate::with('company:id,name')
            ->when($owner === 'global', fn ($q) => $q->whereNull('company_id'))
            ->when($owner === 'clients', fn ($q) => $q->whereNotNull('company_id'))
            ->when($owner === 'deleted', fn ($q) => $q->whereNotNull('client_deleted_at')->whereNull('shared_at'))
            ->orderByDesc('is_default')->latest()->paginate($perPage);
    }

    /** A client: its company's own templates. */
    public function paginateOwn(int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return LabelTemplate::ownedBy($companyId)->latest()->paginate($perPage);
    }

    /** How many own designs the company may still create. */
    public static function slotsLeft(\App\Domain\Companies\Company $company): int
    {
        return max(0, (int) $company->template_limit - LabelTemplate::withoutGlobalScope('visible')->ownedBy($company->id)->count());
    }

    /**
     * A client's new template belongs to its company and is in its "Mine" right away (forCompany).
     * @throws \DomainException when the company has used up its template_limit
     */
    public function storeForCompany(array $data, \App\Domain\Companies\Company $company, int $userId): LabelTemplate
    {
        $template = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $company, $userId) {
            \App\Domain\Companies\Company::lockForUpdate()->find($company->id); // two tabs can't both take the last slot
            if (self::slotsLeft($company->fresh()) < 1) {
                throw new \DomainException('templates.limit_reached');
            }

            return $this->store($data + ['company_id' => $company->id, 'created_by' => $userId, 'watermark' => $data['watermark'] ?? true]);
        });
        \App\Domain\Reporting\AuditLog::record('template.client_created', 'label_template', $template->id, ['company' => $company->id]);

        return $template;
    }

    /** The owner agrees (or not) that the super admin may make the template global. No consent = not global. */
    public function setConsent(LabelTemplate $template, bool $consent): void
    {
        $template->update(['share_consent' => $consent] + ($consent ? [] : ['shared_at' => null]));
        \App\Domain\Reporting\AuditLog::record($consent ? 'template.consent_given' : 'template.consent_withdrawn', 'label_template', $template->id);
    }

    /** A client deletes its design: hidden from the company (and from "Mine"), kept for the super admin. */
    public function clientDelete(LabelTemplate $template): void
    {
        $template->update(['client_deleted_at' => now()]);
        $template->company?->labelTemplates()->detach($template->id);
        \App\Domain\Reporting\AuditLog::record('template.client_deleted', 'label_template', $template->id, ['company' => $template->company_id]);
    }

    /** Super admin: publish a client's template for everyone (only with consent) or take it back. */
    public function setShared(LabelTemplate $template, bool $shared): void
    {
        if ($shared && !$template->share_consent) {
            throw new \DomainException('templates.no_consent');
        }
        $template->update(['shared_at' => $shared ? now() : null]);
        \App\Domain\Reporting\AuditLog::record($shared ? 'template.published' : 'template.unpublished', 'label_template', $template->id, ['company' => $template->company_id]);
    }

    /** @param array{name:string,width_mm:int,height_mm:int,layout:array} $data */
    public function store(array $data): LabelTemplate
    {
        return LabelTemplate::create(['is_default' => false, 'printer_type' => 'pdf_browser'] + $this->withCompiledHtml($data));
    }

    /** Any template, the default one included (it stays the default: is_default is never changed here). */
    public function update(LabelTemplate $template, array $data): LabelTemplate
    {
        $template->update($this->withCompiledHtml(collect($data)->except('is_default')->all()));

        return $template->fresh();
    }

    public function destroy(LabelTemplate $template): void
    {
        $this->guardDefault($template);
        $template->delete();
    }

    private function withCompiledHtml(array $data): array
    {
        $data['template'] = LabelTemplate::compileLayout($data['layout'], $data['width_mm'], $data['height_mm']);

        return $data;
    }

    private function guardDefault(LabelTemplate $template): void
    {
        if ($template->is_default) {
            throw new \DomainException('The default template cannot be deleted.');
        }
    }
}
