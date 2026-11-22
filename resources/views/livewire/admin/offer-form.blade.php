@php
    $locales = \App\Support\AdminI18n::locales();
    $actions = collect(['order' => 'heroicon-o-shopping-bag', 'call' => 'heroicon-o-phone']);
    $actionLabels = $actions->map(fn ($icon, $a) => collect($locales)->mapWithKeys(fn ($l) => [$l => __("offers.action.$a", [], $l)]))->all();
@endphp
<div>
    <x-admin.i18n-form submit="save" :cancel="route('admin.offers', $category)">
        <x-slot:header>
            <x-admin.page-header :back="route('admin.offers', $category)" :back-label="__('nav.group.content') . ' · ' . __('nav.offers') . ' · ' . $category->name_uz"
                                 :title="$offer?->title_uz ?: __('offers.add_item')" />
        </x-slot:header>

        <x-admin.lang-tabs>
            <x-slot:actions>
                {{-- AI fill: only empty fields; anything already typed is replaced only after a confirmation --}}
                <div class="relative" x-data="{ open: false }" x-on:keydown.escape="open = false" data-no-dirty data-ai-fill>
                    <x-admin.button size="sm" icon="sparkles" x-on:click="open = !open" x-bind:aria-expanded="open">{{ __('ai.generate') }}</x-admin.button>
                    <div x-show="open" x-cloak x-on:click.outside="open = false"
                         class="absolute right-0 top-full z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl border border-stone-200 bg-white shadow-xl p-3 space-y-2">
                        <label for="ai-notes" class="block text-[13px] font-medium text-stone-700">{{ __('ai.offer_title') }}</label>
                        <textarea id="ai-notes" wire:model="aiNotes" rows="3" maxlength="1500" placeholder="{{ __('ai.notes_placeholder') }}" class="admin-input text-xs"></textarea>
                        <p class="text-xs text-stone-500">{{ $ai->available() ? __('ai.offer_hint') : __('ai.unavailable.' . $ai->unavailableReason()) }}</p>
                        @if($aiMessage)<p role="status" class="text-xs {{ str_starts_with($aiMessage, '✓') ? 'text-emerald-700' : 'text-rose-700' }}">{{ $aiMessage }}</p>@endif
                        <x-admin.button variant="primary" icon="sparkles" class="w-full" wire:click="generateContent" wire:loading.attr="disabled" wire:target="generateContent" :disabled="!$ai->available()">
                            <span wire:loading.remove wire:target="generateContent">{{ __('ai.generate') }}</span>
                            <span wire:loading wire:target="generateContent">{{ __('common.loading') }}</span>
                        </x-admin.button>
                    </div>
                </div>
            </x-slot:actions>
        </x-admin.lang-tabs>

        <x-admin.section :title="__('offers.sec_texts')" :hint="__('banners.sec_content_hint')">
            <x-admin.i18n-field name="item.title" :label="__('common.name')" :max="$limits['title']" required :live="!$offerId" />
            <x-admin.i18n-field name="item.description" :label="__('offers.description')" type="textarea" rows="4" :max="$limits['description']" />
        </x-admin.section>

        <x-admin.section :title="__('ai.faq')" :hint="__('offers.faq_hint')">
            <x-admin.i18n-repeater :count="count($item['faq'])" :max="$limits['faq']" add="addFaq" :add-label="__('ai.faq_add')">
                @forelse($item['faq'] as $i => $row)
                    <x-admin.i18n-repeater-row name="item.faq.{{ $i }}" :label="__('ai.question') . ' ' . ($i + 1)" remove="removeFaq({{ $i }})" wire:key="faq-{{ $i }}">
                        <x-admin.i18n-field name="item.faq.{{ $i }}.q" :label="__('ai.question')" :max="$limits['q']" :required="true" />
                        <x-admin.i18n-field name="item.faq.{{ $i }}.a" :label="__('ai.answer')" type="textarea" rows="2" :max="$limits['a']" :required="true" />
                    </x-admin.i18n-repeater-row>
                @empty
                    <p class="text-sm text-stone-600">{{ __('offers.faq_empty') }}</p>
                @endforelse
            </x-admin.i18n-repeater>
        </x-admin.section>

        <x-admin.section :title="__('offers.sec_photo')" shared>
            <x-admin.image-upload model="photo" :image="$image" :note="$imageSource ? __('banners.source_' . $imageSource) : null" remove="dropImage"
                                  :icon="$category->icon ?: 'heroicon-o-photo'" :hint="__('banners.image_hint')" :label="__('offers.sec_photo')" />
            <x-admin.prompt-box :title="__('banners.prompt_title')" :hint="__('banners.prompt_hint')" :text="\App\Domain\Content\Banner::imagePrompt($item['title_uz'] ?: null)" />
        </x-admin.section>

        <x-admin.section :title="__('offers.sec_button')" shared>
            <x-admin.radio-cards model="item.action" :legend="__('offers.button')"
                                 :options="$actions->map(fn ($icon, $a) => ['label' => __('offers.action.' . $a), 'hint' => __('offers.action_hint.' . $a), 'icon' => $icon])->all()" />
            <x-admin.field name="item.url" :label="__('offers.item_url')" type="url" placeholder="https://uzum.uz/…" :hint="__('offers.item_url_hint')" />
        </x-admin.section>

        @if($category->slug === 'printers' || $chosenDrivers->isNotEmpty())
            <x-admin.section :title="__('nav.drivers')" :hint="__('offers.drivers_hint')" shared>
                @if($chosenDrivers->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach($chosenDrivers as $d)
                            <x-admin.chip remove="removeDriver({{ $d->id }})" wire:key="drv-{{ $d->id }}">{{ $d->name() }}</x-admin.chip>
                        @endforeach
                    </ul>
                @endif
                <x-admin.field name="addDriver" :label="__('offers.add_driver')" id="f-add-driver" class="max-w-sm">
                    <select id="f-add-driver" wire:model.live="addDriver" class="admin-input" data-no-dirty>
                        <option value="">+ {{ __('offers.add_driver') }}</option>
                        @foreach($driverChoices as $brand => $list)
                            <optgroup label="{{ $brand }}">@foreach($list as $d)<option value="{{ $d->id }}">{{ $d->name() }}</option>@endforeach</optgroup>
                        @endforeach
                    </select>
                </x-admin.field>
            </x-admin.section>
        @endif

        <x-admin.section :title="__('offers.slug')" shared collapsed>
            <p class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-700 font-mono break-all">
                <span class="text-stone-500 shrink-0">@svg('heroicon-o-lock-closed', 'w-4 h-4')</span>/marketplace/{{ $item['slug'] ?: '…' }}
            </p>
            <p class="text-xs text-stone-500">{{ $offerId ? __('offers.slug_hint_fixed') : __('offers.slug_hint') }}</p>
        </x-admin.section>

        <x-slot:aside>
            <x-admin.publish-card model="item.is_active" :view-url="$offer?->is_active ? route('marketplace.item', $offer->slug) : null">
                <dl class="text-sm">
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-600">{{ __('offers.category') }}</dt><dd class="font-medium text-stone-900 truncate">{{ $category->name_uz }}</dd></div>
                </dl>
                @if($offer)<p class="text-xs text-stone-500">{{ __('admin.list.updated') }}: {{ $offer->updated_at?->format('d.m.Y H:i') }}</p>@endif
            </x-admin.publish-card>

            {{-- the catalog card as visitors see it, in the language of the switch --}}
            <div class="bg-white border border-stone-200 rounded-xl overflow-hidden" data-preview>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-stone-200">
                    <h2 class="text-sm font-semibold text-stone-900">{{ __('banners.preview') }}</h2>
                    <span class="text-xs font-semibold text-stone-600" x-text="lang.toUpperCase()"></span>
                </div>
                <div class="p-4 bg-stone-50">
                    <div class="bg-white border border-stone-200 rounded-2xl p-4 max-w-xs mx-auto">
                        @if($image)<img src="{{ $image }}" alt="" class="h-32 w-full object-contain">
                        @else<div class="h-32 w-full rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">@svg($category->icon ?: 'heroicon-o-cube', 'w-10 h-10')</div>@endif
                        <p class="font-semibold text-stone-900 mt-3 leading-snug break-words" x-text="shown('f-item-title') || {{ Js::from(__('banners.preview_empty')) }}">{{ $item['title_uz'] ?: __('banners.preview_empty') }}</p>
                        <p class="text-sm text-stone-600 mt-1 leading-relaxed line-clamp-3" x-show="shown('f-item-description')" x-text="shown('f-item-description')">{{ $item['description_uz'] }}</p>
                        <span class="mt-3 inline-flex w-full items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold"
                              :class="$wire.$get('item.action') === 'call' ? 'border border-stone-900 text-stone-900' : 'bg-amber-700 text-white'"
                              x-text="({{ Js::from($actionLabels) }})[$wire.$get('item.action')]?.[lang]">{{ __('offers.action.' . $item['action']) }}</span>
                    </div>
                    <p x-show="lang !== source && (!changed('f-item-title') || !changed('f-item-description'))" x-cloak class="text-xs text-stone-600 mt-2">{{ __('admin.preview.fallback') }}</p>
                </div>
            </div>
        </x-slot:aside>
    </x-admin.i18n-form>

    <x-admin.confirm-fill :fields="$fillConflicts" :title="__('admin.fill.ai_title')" />
</div>
