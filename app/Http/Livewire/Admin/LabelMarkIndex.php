<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Printing\Models\LabelMark;
use App\Support\AdminI18n;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Super admin: label icons of the designer's "Знаки" menu. Add / edit (replace the image): LabelMarkForm.
 * No delete — saved templates refer to an icon by key; hiding takes it out of the menu only.
 */
#[Layout('layouts.app')]
class LabelMarkIndex extends Component
{
    public string $search = '';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: false)]
    public bool $missing = false;

    public function toggle(int $id): void
    {
        $mark = LabelMark::findOrFail($id);
        $mark->update(['is_active' => !$mark->is_active]);
        \App\Domain\Reporting\AuditLog::record($mark->is_active ? 'label_mark.shown' : 'label_mark.hidden', 'label_mark', $mark->id, ['key' => $mark->key]);
        $this->dispatch('notify', type: 'success', message: __('admin.list.status_changed'));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'group', 'missing');
    }

    public function render()
    {
        $all = LabelMark::orderBy('sort')->orderBy('id')->get();
        $q = mb_strtolower(trim($this->search));
        $marks = $all
            ->when(in_array($this->group, LabelMark::GROUPS, true), fn ($c) => $c->where('group', $this->group))
            ->when($this->missing, fn ($c) => $c->filter(fn ($m) => AdminI18n::isIncomplete($m, LabelMark::TRANSLATABLE)))
            ->when($q !== '', fn ($c) => $c->filter(fn ($m) => str_contains(mb_strtolower($m->key . ' ' . $m->name_uz . ' ' . $m->name_ru . ' ' . $m->name_zh), $q)))
            ->values();

        return view('livewire.admin.label-mark-index', [
            'marks' => $marks,
            'total' => $all->count(),
            'groups' => ['' => ['label' => __('common.all'), 'count' => $all->count()]]
                + collect(LabelMark::GROUPS)->mapWithKeys(fn ($g) => [$g => ['label' => __("labels.marks_group.$g"), 'count' => $all->where('group', $g)->count()]])->all(),
            'missingCount' => $all->filter(fn ($m) => AdminI18n::isIncomplete($m, LabelMark::TRANSLATABLE))->count(),
        ]);
    }
}
