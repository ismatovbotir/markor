<?php

namespace App\Http\Livewire\Printing;

use App\Domain\Printing\Models\LabelTemplate;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use App\Domain\Printing\Renderers\LayoutRenderer;
use App\Domain\Printing\Services\LabelPdfService;
use App\Domain\Printing\Services\LabelTemplateService;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class LabelTemplateIndex extends Component
{
    use WithPagination;

    public bool $showForm  = false;
    public ?int $editingId = null;

    /** Initial state handed to the visual editor: name, width_mm, height_mm, elements. */
    public array $editor = [];

    /** Bumped on every open so the editor re-initialises. */
    public int $editorKey = 0;

    /** Client designer: the owner agrees that the super admin may make the template available to everyone. */
    public bool $shareConsent = false;

    /** Super admin list filter: '' all, 'global', 'clients'. */
    #[\Livewire\Attributes\Url(as: 'owner', except: '')]
    public string $owner = '';

    /** New template always starts as a copy — of the default one unless another is given. */
    public function openCopy(?int $id = null): void
    {
        $this->authorize('create', LabelTemplate::class);

        $source = $id ? LabelTemplate::findOrFail($id) : LabelTemplate::default();

        $this->editingId = null;
        $this->shareConsent = false;
        $this->open($source, $source->name . ' — ' . __('templates.copy_suffix'));
    }

    public function openEdit(int $id): void
    {
        $t = LabelTemplate::findOrFail($id);
        $this->authorize('update', $t);

        $this->editingId = $id;
        $this->shareConsent = (bool) $t->share_consent;
        $this->open($t, $t->name);
    }

    private function open(LabelTemplate $source, string $name): void
    {
        $this->editor = [
            'useSources'   => true,
            'allowMarking' => true,
            'allowLogo'    => true, // company logo element: drawn with the batch company's logo
            'sampleLogo'   => \App\Domain\Printing\Services\LabelImage::sample()['uri'],
            'watermark'    => (bool) $source->watermark,
            'fields'       => collect(LayoutRenderer::markingFields())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
            'name'      => $name,
            'width_mm'  => $source->width_mm,
            'height_mm' => $source->height_mm,
            'columns'   => $source->columns ?: 1,
            'gap_mm'    => (float) $source->gap_mm,
            'labels_per_pdf' => (int) ($source->labels_per_pdf ?: 2000),
            // Templates made before the visual editor have no layout: start them from the default one.
            'elements'  => self::bindPlaceholders($source->layout ?: LabelTemplate::DEFAULT_LAYOUT),
        ];
        $this->editorKey++;
        $this->showForm = true;
    }

    /**
     * Older layouts used text like "{gtin}". A text that is exactly one known placeholder becomes a
     * dropdown binding; mixed text ("SN {serial}") stays as text with the placeholder.
     */
    private static function bindPlaceholders(array $elements): array
    {
        $fields = array_keys(LayoutRenderer::markingFields());

        return array_map(function ($el) use ($fields) {
            if (($el['type'] ?? '') === 'text' && empty($el['source']) && preg_match('/^\{(\w+)\}$/', trim($el['text'] ?? ''), $m)) {
                if (in_array($m[1], $fields, true)) {
                    $el['source'] = $m[1];
                    $el['text'] = '';
                } elseif ($m[1] === 'date') {
                    $el = ['source' => '@date', 'fmt' => 'DD.MM.YYYY', 'offset_days' => 0, 'text' => ''] + $el;
                }
            }

            return $el;
        }, $elements);
    }

    public function save(array $payload, LabelTemplateService $service): void
    {
        $existing = $this->editingId ? LabelTemplate::findOrFail($this->editingId) : null;
        $this->authorize($existing ? 'update' : 'create', $existing ?? LabelTemplate::class);

        $validator = Validator::make($payload, [
            'name'                 => 'required|string|max:255',
            'width_mm'             => 'required|integer|min:10|max:300',
            'height_mm'            => 'required|integer|min:10|max:300',
            'columns'              => 'required|integer|min:1|max:10',
            'gap_mm'               => 'required|numeric|min:0|max:30',
            'labels_per_pdf'       => 'nullable|integer|min:100|max:10000',
            'elements'             => 'required|array|min:1|max:40',
            'watermark'            => 'nullable|boolean',
            'elements.*.type'      => 'required|in:datamatrix,text,barcode,logo,mark',
            'elements.*.mark'      => ['nullable', 'required_if:elements.*.type,mark', \Illuminate\Validation\Rule::in(\App\Domain\Printing\Models\LabelMark::keys())],
            'elements.*.symbology' => 'nullable|in:qr,datamatrix,code128,ean13',
            'elements.*.source'    => 'nullable|string|max:20',
            'elements.*.caption'   => 'nullable|boolean',
            'elements.*.fmt'         => 'nullable|string|max:40',
            'elements.*.offset_days' => 'nullable|integer|min:-3650|max:3650',
            'elements.*.prefix'      => 'nullable|string|max:30',
            'elements.*.suffix'      => 'nullable|string|max:30',
            'elements.*.start'       => 'nullable|integer|min:0|max:999999999',
            'elements.*.step'        => 'nullable|integer|min:-1000|max:1000',
            'elements.*.pad'         => 'nullable|integer|min:0|max:12',
            'elements.*.x'         => 'required|numeric|min:0|max:300',
            'elements.*.y'         => 'required|numeric|min:0|max:300',
            'elements.*.w'         => 'required|numeric|min:1|max:300',
            'elements.*.h'         => 'required|numeric|min:1|max:300',
            'elements.*.text'      => 'nullable|string|max:2000',
            'elements.*.font_size' => 'nullable|numeric|min:3|max:72',
            'elements.*.bold'      => 'nullable|boolean',
            'elements.*.font'      => ['nullable', \Illuminate\Validation\Rule::in(\App\Domain\Printing\LabelFonts::keys())], // empty = DejaVu Sans
            'elements.*.align'     => 'nullable|in:left,center,right',
            'elements.*.rotation'  => 'nullable|integer|in:0,90,180,270',
            'elements.*.hrt'       => 'nullable|boolean',             // EAN-13 / Code 128: digits under the bars
        ]);

        if ($validator->fails()) {
            $this->dispatch('notify', type: 'error', message: $validator->errors()->first());
            return;
        }

        $data = $validator->validated();

        if (!collect($data['elements'])->contains('type', 'datamatrix')) {
            $this->dispatch('notify', type: 'error', message: __('templates.need_datamatrix'));
            return;
        }

        $data = [
            'name'      => $data['name'],
            'width_mm'  => (int) $data['width_mm'],
            'height_mm' => (int) $data['height_mm'],
            'columns'   => (int) $data['columns'],
            'gap_mm'    => (float) $data['gap_mm'],
            'labels_per_pdf' => (int) ($data['labels_per_pdf'] ?? 2000),
            'watermark' => (bool) ($data['watermark'] ?? false),
            'layout'    => array_values($data['elements']),
        ];

        $user = auth()->user();
        if ($user->isAdmin()) {
            $existing ? $service->update($existing, $data) : $service->store($data);
        } else {
            try {
                $template = $existing ? $service->update($existing, $data) : $service->storeForCompany($data, $user->company, $user->id);
            } catch (\DomainException $e) {
                $this->dispatch('notify', type: 'error', message: __($e->getMessage(), ['limit' => $user->company->template_limit]));
                return;
            }
            if ((bool) $template->share_consent !== $this->shareConsent) {
                $service->setConsent($template, $this->shareConsent);
            }
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    /** Super admin: make a client's template available to everyone (needs the client's consent) or take it back. */
    public function share(int $id, bool $shared, LabelTemplateService $service): void
    {
        $t = LabelTemplate::findOrFail($id);
        abort_unless(auth()->user()->isAdmin() && $t->company_id !== null, 403);
        if ($shared) {
            $this->authorize('publish', $t);
        }
        $service->setShared($t, $shared);
        $this->dispatch('notify', type: 'success', message: __($shared ? 'templates.published' : 'templates.unpublished'));
    }

    public function delete(int $id, LabelTemplateService $service): void
    {
        $t = LabelTemplate::findOrFail($id);
        $this->authorize('delete', $t);
        auth()->user()->isAdmin() ? $service->destroy($t) : $service->clientDelete($t);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(LabelTemplateService $service, LabelPdfService $pdf, DataMatrixRenderer $renderer)
    {
        $sample = $this->showForm ? $pdf->demoCode() : null;

        return view('livewire.printing.label-template-index', [
            'templates' => $service->paginate(20, $this->owner),
            'sampleDm'  => $sample ? $renderer->renderAsBase64($sample, 4) : null,
            'sample'    => $sample ? LayoutRenderer::markingRow($sample, 'partiya_01') : null,
        ]);
    }
}
