<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\NewsPost;
use App\Support\AdminI18n;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super admin: news posts (/news) list. Create / edit: NewsForm. */
#[Layout('layouts.app')]
class NewsIndex extends Component
{
    public const STATUSES = ['all', 'published', 'scheduled', 'hidden'];

    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    /** Only posts with an empty ru / zh field (the menu badge opens the list with ?missing=1). */
    #[Url(except: false)]
    public bool $missing = false;

    public function toggle(int $id): void
    {
        $post = NewsPost::findOrFail($id);
        $post->update(['is_active' => !$post->is_active]);
        $this->dispatch('notify', type: 'success', message: __('admin.list.status_changed'));
    }

    public function delete(int $id): void
    {
        NewsPost::findOrFail($id)->delete();
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'missing');
    }

    public function render()
    {
        $all = NewsPost::query()->orderByDesc('published_at')->orderByDesc('id')->get();
        $status = in_array($this->status, self::STATUSES, true) ? $this->status : 'all';
        $q = mb_strtolower(trim($this->search));

        $posts = $all
            ->when($status !== 'all', fn ($c) => $c->filter(fn (NewsPost $p) => $p->status() === $status))
            ->when($this->missing, fn ($c) => $c->filter(fn (NewsPost $p) => AdminI18n::isIncomplete($p, NewsPost::TRANSLATABLE)))
            ->when($q !== '', fn ($c) => $c->filter(fn (NewsPost $p) => collect(AdminI18n::locales())
                ->contains(fn ($l) => str_contains(mb_strtolower((string) $p->{"title_$l"}), $q))))
            ->values();

        return view('livewire.admin.news-index', [
            'posts' => $posts,
            'total' => $all->count(),
            'statuses' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => [
                'label' => $s === 'all' ? __('common.all') : __("admin.status.$s"),
                'count' => $s === 'all' ? $all->count() : $all->filter(fn (NewsPost $p) => $p->status() === $s)->count(),
            ]])->all(),
            'missingCount' => $all->filter(fn (NewsPost $p) => AdminI18n::isIncomplete($p, NewsPost::TRANSLATABLE))->count(),
        ]);
    }
}
