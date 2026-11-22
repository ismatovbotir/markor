<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\Banner;
use App\Support\AdminI18n;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super admin: home page banners list (order, publish, delete). Create / edit is a separate page (BannerForm). */
#[Layout('layouts.app')]
class BannerIndex extends Component
{
    public const STATUSES = ['all', 'published', 'scheduled', 'hidden'];

    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    /** Only banners with an empty ru / zh text (the menu badge opens the list with ?missing=1). */
    #[Url(except: false)]
    public bool $missing = false;

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
        $this->dispatch('notify', type: 'success', message: __('admin.list.status_changed'));
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

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'missing');
    }

    public function render()
    {
        $default = Banner::where('is_default', true)->first();
        $all = Banner::with('offer')->where('is_default', false)->orderBy('sort_order')->orderBy('id')->get();
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $q = mb_strtolower(trim($this->search));
        $incomplete = fn (Banner $b) => AdminI18n::isIncomplete($b, Banner::TRANSLATION_CHECK);

        $banners = $all
            ->when($status !== 'all', fn ($c) => $c->filter(fn (Banner $b) => $b->status() === $status))
            ->when($this->missing, fn ($c) => $c->filter($incomplete))
            ->when($q !== '', fn ($c) => $c->filter(fn (Banner $b) => collect(AdminI18n::locales())
                ->contains(fn ($l) => str_contains(mb_strtolower((string) $b->{"title_$l"}), $q))))
            ->values();
        $filtered = $banners->count() !== $all->count();

        return view('livewire.admin.banner-index', [
            'default' => $filtered ? null : $default,   // the built-in banner is shown with the whole list only
            'banners' => $banners,
            'total' => $all->count(),
            'canMove' => !$filtered,                    // the order is changed in the whole list only
            'statuses' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'label' => $s === 'all' ? __('common.all') : __("admin.status.$s"),
                'count' => $s === 'all' ? $all->count() : $all->filter(fn (Banner $b) => $b->status() === $s)->count(),
            ]])->all(),
            'missingCount' => $all->filter($incomplete)->count(),
        ]);
    }
}
