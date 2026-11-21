<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\Banner;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: home page banners list (order, publish, delete). Create / edit is a separate page (BannerForm). */
#[Layout('layouts.app')]
class BannerIndex extends Component
{
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
        if ($banner->image_path && !str_starts_with($banner->image_path, 'images/')) {
            Storage::disk('public')->delete($banner->image_path);
        }
        $banner->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render()
    {
        return view('livewire.admin.banner-index', [
            'banners' => Banner::with('offer')->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
