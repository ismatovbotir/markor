<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Settings\Setting;
use App\Http\Middleware\SetLocale;
use App\Support\Seo;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: titles / descriptions of the public pages, verification, analytics, robots.txt, sitemap. */
#[Layout('layouts.app')]
class SeoPage extends Component
{
    public string $locale = 'uz';
    /** [page][field][locale] => override text */
    public array $texts = [];
    public array $codes = ['google' => '', 'yandex' => '', 'bing' => '', 'ga4' => '', 'metrika' => ''];
    public bool $noindex = false;
    public string $robotsExtra = '';
    public string $aiAgents = 'all';

    public function mount(): void
    {
        foreach (Seo::PAGES as $page) {
            foreach (array_keys(Seo::FIELDS) as $field) {
                foreach (array_keys(SetLocale::LOCALES) as $locale) {
                    $this->texts[$page][$field][$locale] = Seo::get("$page.$field.$locale") ?? '';
                }
            }
        }
        foreach (array_keys($this->codes) as $key) {
            $this->codes[$key] = Seo::get($key) ?? '';
        }
        $this->noindex = Seo::noindex();
        $this->robotsExtra = Seo::get('robots_extra') ?? '';
        $this->aiAgents = Seo::aiAgents();
    }

    public function save(): void
    {
        $this->validate([
            'texts.*.title.*'       => 'nullable|string|max:120',
            'texts.*.description.*' => 'nullable|string|max:320',
            'aiAgents'      => 'required|in:all,search,none',
            'codes.google'  => ['nullable', 'regex:/^[A-Za-z0-9_\-]{10,100}$/'],
            'codes.yandex'  => ['nullable', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'codes.bing'    => ['nullable', 'regex:/^[A-Za-z0-9]{8,40}$/'],
            'codes.ga4'     => ['nullable', 'regex:/^G-[A-Z0-9]{4,15}$/'],
            'codes.metrika' => ['nullable', 'regex:/^\d{5,12}$/'],
            'robotsExtra'   => ['nullable', 'string', 'max:2000', 'regex:/^((User-agent|Disallow|Allow|Crawl-delay|Sitemap|Host|Clean-param):[^\r\n]*|#[^\r\n]*|\s*)(\r?\n((User-agent|Disallow|Allow|Crawl-delay|Sitemap|Host|Clean-param):[^\r\n]*|#[^\r\n]*|\s*))*$/i'],
        ]);

        $values = ['seo.ai_agents' => $this->aiAgents, 'seo.noindex' => $this->noindex ? '1' : null, 'seo.robots_extra' => $this->robotsExtra, 'seo.updated_at' => now()->toDateString()];
        foreach ($this->codes as $key => $value) {
            $values["seo.$key"] = $value;
        }
        foreach ($this->texts as $page => $fields) {
            foreach ($fields as $field => $locales) {
                foreach ($locales as $locale => $value) {
                    $values["seo.$page.$field.$locale"] = $value;
                }
            }
        }
        Setting::put($values);
        $this->dispatch('notify', type: 'success', message: __('common.saved'));
    }

    public function render()
    {
        return view('livewire.admin.seo-page', ['locales' => SetLocale::LOCALES]);
    }
}
