<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Content\NewsPost;
use App\Support\AdminI18n;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: one news post — title, short text and body (Markdown) in uz / ru / zh, publish date. */
#[Layout('layouts.app')]
class NewsForm extends Component
{
    public ?int $postId = null;
    public array $item = [];

    public function mount(?NewsPost $post = null): void
    {
        $this->item = ['source_name' => '', 'source_url' => '', 'published_at' => now()->format('Y-m-d\TH:i'), 'is_active' => true];
        foreach (NewsPost::TRANSLATABLE as $field) {
            foreach (AdminI18n::locales() as $locale) {
                $this->item["{$field}_$locale"] = '';
            }
        }

        if ($post?->exists) {
            $this->postId = $post->id;
            $this->item = array_merge($this->item, collect($post->only(array_keys($this->item)))->map(fn ($v) => $v ?? '')->all(),
                ['published_at' => $post->published_at?->format('Y-m-d\TH:i') ?? '', 'is_active' => $post->is_active]);
        }
    }

    public function save()
    {
        $rules = collect(NewsPost::rules())->mapWithKeys(fn ($r, $k) => ["item.$k" => $r])->all();
        $rules['item.published_at'] = 'required|date';
        $rules['item.source_url'] .= '|unique:news_posts,source_url' . ($this->postId ? ',' . $this->postId : '');
        $data = $this->validate($rules)['item'];

        $values = collect($data)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v)->all();
        // the address is fixed once created, so shared links keep working after the title is edited
        $current = $this->postId ? NewsPost::findOrFail($this->postId) : null;
        $values['slug'] = $current?->slug ?? NewsPost::freeSlug($values['title_uz']);

        if ($current) {
            // stay on the page: translating means several saves in a row
            $current->update($values);
            $this->dispatch('notify', type: 'success', message: __('common.saved'));
            $this->dispatch('saved');

            return null;
        }

        // a new post continues on its own edit address
        $post = NewsPost::create($values);
        session()->flash('notify', __('common.saved'));

        return $this->redirectRoute('admin.news.edit', $post, navigate: false);
    }

    public function render()
    {
        $post = $this->postId ? NewsPost::find($this->postId) : null;
        $rules = NewsPost::rules();

        return view('livewire.admin.news-form', [
            'post' => $post,
            'limits' => collect(NewsPost::TRANSLATABLE)->mapWithKeys(fn ($f) => [$f => AdminI18n::max($rules, "{$f}_uz")])->all()
                + ['source_name' => AdminI18n::max($rules, 'source_name'), 'source_url' => AdminI18n::max($rules, 'source_url')],
        ]);
    }
}
