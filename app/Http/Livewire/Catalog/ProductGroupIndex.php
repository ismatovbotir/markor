<?php

namespace App\Http\Livewire\Catalog;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Catalog\Services\ProductGroupService;
use App\Domain\Marking\Parsers\MarkingCodeParser;
use App\Domain\Printing\Renderers\DataMatrixRenderer;
use App\Domain\Marking\Models\MarkingCode;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: Asl Belgisi product categories and the DataMatrix structure of their codes. */
#[Layout('layouts.app')]
class ProductGroupIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $name_uz = '';
    public ?string $name_ru = '';
    public ?string $name_zh = '';
    /** @var array<int, array{ai:string, label:string, length:int, variable:bool}> */
    public array $segments = [];
    public ?string $sample_code = '';
    public bool $requires_eds = false;
    public bool $is_active = true;
    public int $sort_order = 0;

    protected function rules(): array
    {
        return [
            'name_uz'             => 'required|string|max:255',
            'name_ru'             => 'nullable|string|max:255',
            'name_zh'             => 'nullable|string|max:255',
            'segments'            => 'required|array|min:2|max:12',
            'segments.*.ai'       => ['nullable', 'regex:/^\d{2,4}$/'],
            'segments.*.label'    => 'nullable|string|max:60',
            'segments.*.length'   => 'required|integer|min:1|max:90',
            'segments.*.variable' => 'boolean',
            'sample_code'         => 'nullable|string|max:300',
            'requires_eds'        => 'boolean',
            'is_active'           => 'boolean',
            'sort_order'          => 'integer|min:0|max:999',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', ProductGroup::class);
        $this->resetValidation();
        $this->reset(['name_uz', 'name_ru', 'name_zh', 'sample_code', 'requires_eds', 'editingId']);
        $this->is_active  = true;
        $this->sort_order = (int) ProductGroup::max('sort_order') + 1;
        $this->segments   = [
            ['ai' => '01', 'label' => 'GTIN', 'length' => 14, 'variable' => false],
            ['ai' => '21', 'label' => 'Serial', 'length' => 13, 'variable' => true],
            ['ai' => '91', 'label' => 'Verification key', 'length' => 4, 'variable' => true],
            ['ai' => '92', 'label' => 'Verification code', 'length' => 44, 'variable' => true],
        ];
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $group = ProductGroup::findOrFail($id);
        $this->authorize('update', $group);
        $this->resetValidation();
        $this->fill($group->only(['name_uz', 'name_ru', 'name_zh', 'sample_code', 'requires_eds', 'is_active', 'sort_order']));
        $this->segments  = $group->structure ?: [['ai' => '01', 'label' => 'GTIN', 'length' => 14, 'variable' => false]];
        $this->editingId = $id;
        $this->showForm  = true;
    }

    public function addSegment(): void
    {
        $this->segments[] = ['ai' => '', 'label' => '', 'length' => 4, 'variable' => true];
    }

    public function removeSegment(int $i): void
    {
        unset($this->segments[$i]);
        $this->segments = array_values($this->segments);
    }

    public function moveSegment(int $i, int $dir): void
    {
        $j = $i + $dir;
        if (isset($this->segments[$i], $this->segments[$j])) {
            [$this->segments[$i], $this->segments[$j]] = [$this->segments[$j], $this->segments[$i]];
        }
    }

    public function save(ProductGroupService $service): void
    {
        $group = $this->editingId ? ProductGroup::findOrFail($this->editingId) : null;
        $this->authorize($group ? 'update' : 'create', $group ?? ProductGroup::class);

        $data = $this->validate();
        $data['structure'] = array_map(fn ($s) => [
            'ai'       => (string) ($s['ai'] ?? ''),
            'label'    => trim((string) ($s['label'] ?? '')),
            'length'   => (int) $s['length'],
            'variable' => (bool) ($s['variable'] ?? false),
        ], array_values($data['segments']));
        unset($data['segments']);

        if (!collect($data['structure'])->contains('ai', '01')) {
            $this->addError('segments', __('product_groups.need_gtin'));
            return;
        }
        if (filled($data['sample_code']) && MarkingCodeParser::split((new MarkingCodeParser)->normalize($data['sample_code']), $data['structure']) === null) {
            $this->addError('sample_code', __('product_groups.sample_mismatch'));
            return;
        }

        $data['code_format'] = $group?->code_format ?? 'custom';
        $group ? $service->update($group, $data) : $service->store($data);

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function delete(int $id, ProductGroupService $service): void
    {
        $group = ProductGroup::findOrFail($id);
        $this->authorize('delete', $group);
        $service->destroy($group);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(ProductGroupService $service, DataMatrixRenderer $renderer)
    {
        // Live check of the sample code against the structure being edited.
        $test = null;
        if ($this->showForm && filled($this->sample_code)) {
            $code = (new MarkingCodeParser)->normalize($this->sample_code);
            $structure = array_map(fn ($s) => ['ai' => (string) ($s['ai'] ?? ''), 'length' => (int) ($s['length'] ?? 0), 'variable' => (bool) ($s['variable'] ?? false), 'label' => $s['label'] ?? ''], $this->segments);
            $fields = MarkingCodeParser::split($code, $structure);
            $test = ['length' => strlen($code), 'expected' => ProductGroup::lengthOf($structure), 'fields' => $fields, 'image' => null];
            if ($fields !== null) {
                try {
                    $payload = MarkingCodeParser::gs1Payload($code, $structure);
                    $test['image'] = $renderer->pngFromPayload($payload, 4);
                    $test['hri'] = collect($structure)->map(fn ($s) => ($s['ai'] !== '' ? "({$s['ai']})" : '') . $fields[$s['ai'] === '' ? '_' : $s['ai']])->implode('');
                } catch (\Throwable) {
                }
            }
        }

        return view('livewire.catalog.product-group-index', [
            'groups' => $service->paginate($this->search),
            'test'   => $test,
            'counts' => MarkingCode::withoutGlobalScope('owner')->selectRaw('product_group_id, count(*) as n')->groupBy('product_group_id')->pluck('n', 'product_group_id'),
        ]);
    }
}
