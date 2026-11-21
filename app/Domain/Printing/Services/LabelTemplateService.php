<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Models\LabelTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LabelTemplateService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return LabelTemplate::orderByDesc('is_default')->latest()->paginate($perPage);
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
