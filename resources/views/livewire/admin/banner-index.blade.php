<div>
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.banners') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('banners.hint') }}</p>
        </div>
        <a href="{{ route('admin.banners.create') }}" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">+ {{ __('banners.add') }}</a>
    </div>

    <div class="bg-white border border-stone-200 rounded-xl divide-y divide-stone-100">
        @foreach($banners as $b)
            <div class="flex items-center gap-4 px-4 py-3" wire:key="banner-{{ $b->id }}">
                <span class="w-6 text-xs text-stone-400 text-right">{{ $loop->iteration }}</span>
                <div class="w-28 h-14 rounded-lg overflow-hidden shrink-0 flex items-center justify-center {{ ['light' => 'bg-stone-100', 'dark' => 'bg-stone-900', 'amber' => 'bg-amber-700'][$b->style] ?? 'bg-stone-100' }}">
                    @if($b->is_default)<img src="/images/logo.png" alt="" class="h-5 w-auto">
                    @elseif($b->imageUrl())<img src="{{ $b->imageUrl() }}" alt="" class="h-full w-full object-contain">
                    @else<span class="text-xs {{ $b->style === 'light' ? 'text-stone-500' : 'text-white' }}">{{ __('banners.no_image') }}</span>@endif
                </div>
                <div class="flex-1 min-w-0">
                    @if($b->is_default)
                        <p class="font-medium flex items-center gap-2">{{ __('home.h1') }} <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-normal">@svg('heroicon-o-star', 'w-3 h-3 inline') {{ __('banners.default') }}</span></p>
                        <p class="text-xs text-stone-500">{{ __('banners.default_hint') }}</p>
                    @else
                        <p class="font-medium truncate"><a href="{{ route('admin.banners.edit', $b) }}" class="hover:text-amber-700">{{ $b->title_uz }}</a> @if($b->is_sponsored)<span class="ml-1 px-1.5 py-0.5 rounded bg-stone-100 text-stone-500 text-[10px] font-normal">{{ __('banners.ad') }}</span>@endif</p>
                        <p class="text-xs text-stone-500 truncate">@if($b->offer)<span class="text-amber-700">@svg('heroicon-o-shopping-bag', 'w-3 h-3 inline') {{ $b->offer->title_uz }}</span> · @endif{{ $b->link() ?: '—' }}
                            @if($b->starts_at || $b->ends_at) · {{ $b->starts_at?->format('d.m.Y') ?? '…' }} – {{ $b->ends_at?->format('d.m.Y') ?? '…' }}@endif</p>
                    @endif
                </div>
                @unless($b->is_default)
                    <div class="flex items-center gap-1 shrink-0 text-xs">
                        <button wire:click="move({{ $b->id }}, -1)" class="px-1.5 text-stone-400 hover:text-stone-800" title="↑">↑</button>
                        <button wire:click="move({{ $b->id }}, 1)" class="px-1.5 text-stone-400 hover:text-stone-800" title="↓">↓</button>
                        <label class="inline-flex items-center gap-1.5 px-2 cursor-pointer select-none {{ $b->is_active ? 'text-emerald-700' : 'text-stone-500' }}" title="{{ __('banners.publish') }}">
                            <input type="checkbox" wire:click="toggle({{ $b->id }})" @checked($b->is_active) class="rounded border-stone-300 text-emerald-600">
                            {{ __('banners.publish') }}
                        </label>
                        <a href="{{ route('admin.banners.edit', $b) }}" class="px-2 text-stone-500 hover:text-amber-700">{{ __('common.edit') }}</a>
                        <button wire:click="delete({{ $b->id }})" wire:confirm="{{ __('common.confirm_delete') }}" class="px-2 text-stone-500 hover:text-rose-600">{{ __('common.delete') }}</button>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>

</div>
