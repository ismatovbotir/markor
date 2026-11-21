<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\Banner;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Super admin: home page banners. The default banner (built-in hero) is always first and can't be removed. */
#[Layout('layouts.app')]
class BannerIndex extends Component
{
    use WithFileUploads;

    public bool $showForm = false;
    public ?int $editingId = null;
    public array $item = [];
    public $photo = null;

    private function blank(): array
    {
        return ['title_uz' => '', 'title_ru' => '', 'title_zh' => '', 'text_uz' => '', 'text_ru' => '', 'text_zh' => '',
            'button_uz' => '', 'button_ru' => '', 'button_zh' => '', 'url' => '', 'is_sponsored' => false, 'style' => 'light', 'is_active' => true,
            'starts_at' => '', 'ends_at' => '', 'image_path' => null];
    }

    public function openCreate(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'photo']);
        $this->item = $this->blank();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $banner = Banner::where('is_default', false)->findOrFail($id);
        $this->resetValidation();
        $this->photo = null;
        $this->editingId = $id;
        $this->item = array_merge($this->blank(), $banner->only(array_keys($this->blank())), [
            'starts_at' => $banner->starts_at?->toDateString() ?? '', 'ends_at' => $banner->ends_at?->toDateString() ?? '',
        ]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'item.title_uz' => 'required|string|max:160', 'item.title_ru' => 'nullable|string|max:160', 'item.title_zh' => 'nullable|string|max:160',
            'item.text_uz' => 'nullable|string|max:400', 'item.text_ru' => 'nullable|string|max:400', 'item.text_zh' => 'nullable|string|max:400',
            'item.button_uz' => 'nullable|string|max:60', 'item.button_ru' => 'nullable|string|max:60', 'item.button_zh' => 'nullable|string|max:60',
            'item.url'       => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)#i'],
            'item.style'     => 'required|in:' . implode(',', Banner::STYLES),
            'item.is_active' => 'boolean',
            'item.is_sponsored' => 'boolean',
            'item.starts_at' => 'nullable|date', 'item.ends_at' => 'nullable|date|after_or_equal:item.starts_at',
            'photo'          => ['nullable', 'image', 'max:3072', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp'],
        ])['item'];

        $values = collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->except('image_path')->all();
        if ($this->photo) {
            $values['image_path'] = $this->photo->store('banners', 'public');
        }

        if ($this->editingId) {
            $banner = Banner::where('is_default', false)->findOrFail($this->editingId);
            if (isset($values['image_path']) && $banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $banner->update($values);
        } else {
            Banner::create($values + ['sort_order' => (int) Banner::max('sort_order') + 1]);
        }

        $this->showForm = false;
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function move(int $id, int $dir): void
    {
        $list = Banner::where('is_default', false)->orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $list->search(fn ($b) => $b->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $list->count()) {
            return;
        }
        [$a, $b] = [$list[$i], $list[$j]];
        $list->each(fn ($banner, $k) => $banner->update(['sort_order' => $k + 1]));
        $a->update(['sort_order' => $j + 1]);
        $b->update(['sort_order' => $i + 1]);
    }

    public function toggle(int $id): void
    {
        $banner = Banner::where('is_default', false)->findOrFail($id);
        $banner->update(['is_active' => !$banner->is_active]);
    }

    public function delete(int $id): void
    {
        $banner = Banner::where('is_default', false)->findOrFail($id); // the default banner can never be deleted
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        return view('livewire.admin.banner-index', [
            'banners' => Banner::orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
