<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Printing\Models\LabelMark;
use App\Domain\Reporting\AuditLog;
use App\Support\AdminI18n;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Super admin: one label icon — name (uz/ru/zh), menu group, image (upload / replace), source and licence, order, visibility.
 * The key is fixed once created (saved templates refer to it). A replaced image changes every label that uses the icon;
 * the old file is kept. There is no delete.
 */
#[Layout('layouts.app')]
class LabelMarkForm extends Component
{
    use WithFileUploads;

    public ?int $markId = null;
    public array $item = [];
    public $photo = null;

    public function mount(?LabelMark $mark = null): void
    {
        $this->item = ['key' => '', 'group' => 'marking', 'source' => '', 'sort' => (int) LabelMark::max('sort') + 10, 'is_active' => true];
        foreach (AdminI18n::locales() as $l) {
            $this->item["name_$l"] = '';
        }
        if ($mark?->exists) {
            $this->markId = $mark->id;
            $this->item = array_merge($this->item, collect($mark->only(array_keys($this->item)))->map(fn ($v) => $v ?? '')->all(), ['is_active' => $mark->is_active]);
        }
    }

    private function photoRules(bool $required): array
    {
        return ['photo' => [$required ? 'required' : 'nullable', 'image', 'max:1024', 'mimes:png,jpg,jpeg', 'extensions:png,jpg,jpeg', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000']];
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo', $this->photoRules(false));
    }

    public function save()
    {
        $mark = $this->markId ? LabelMark::findOrFail($this->markId) : null;
        $rules = collect(LabelMark::rules())->mapWithKeys(fn ($r, $k) => ["item.$k" => $r])->all() + ['item.is_active' => 'boolean'];
        if (!$mark) {
            $rules['item.key'] = ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:label_marks,key'];
        }
        $data = $this->validate($rules + $this->photoRules(!$mark), [], ['photo' => __('label_marks.image'), 'item.key' => __('label_marks.field_key')]);
        $values = collect($data['item'])->except('key')->map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v))->all();
        $values['sort'] = (int) ($values['sort'] ?? 0);

        if ($this->photo) {
            [$w, $h] = getimagesize($this->photo->getRealPath());
            $values += ['path' => 'storage/' . $this->photo->store('label-marks', 'public'), 'width' => $w, 'height' => $h];
        }

        if ($mark) {
            $old = $mark->path;
            $mark->update($values);
            if ($this->photo) {
                AuditLog::record('label_mark.image_replaced', 'label_mark', $mark->id, ['key' => $mark->key, 'old' => $old, 'new' => $mark->path]);
            }
            $this->reset('photo');
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            $this->dispatch('saved');

            return null;
        }

        $mark = LabelMark::create($values + ['key' => $data['item']['key']]);
        AuditLog::record('label_mark.created', 'label_mark', $mark->id, ['key' => $mark->key]);
        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.label-marks.edit', $mark, navigate: false);
    }

    public function render()
    {
        $mark = $this->markId ? LabelMark::find($this->markId) : null;
        $image = $this->photo && !$this->getErrorBag()->has('photo') ? $this->photo->temporaryUrl() : $mark?->url();

        return view('livewire.admin.label-mark-form', [
            'mark' => $mark,
            'image' => $image,
            'limits' => ['name' => AdminI18n::max(LabelMark::rules(), 'name_uz'), 'source' => AdminI18n::max(LabelMark::rules(), 'source')],
            'groups' => collect(LabelMark::GROUPS)->mapWithKeys(fn ($g) => [$g => __("labels.marks_group.$g")])->all(),
        ]);
    }
}
