<div>
    @php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.templates') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('templates.catalog_hint') }}</p>
        </div>
        @if(auth()->user()->company_id)
            <button wire:click="openOrder" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-700 text-white rounded-lg text-sm hover:bg-amber-800">@svg('heroicon-o-plus', 'w-4 h-4') {{ __('template_requests.order') }}</button>
        @endif
    </div>

    @if($requests->isNotEmpty())
        <div class="bg-white border border-stone-200 rounded-xl p-4 mb-6">
            <p class="text-sm font-semibold mb-2">{{ __('template_requests.mine') }}</p>
            <ul class="divide-y divide-stone-100 text-sm">
                @foreach($requests as $r)
                    @php $c = ['new' => 'amber', 'done' => 'emerald', 'rejected' => 'stone'][$r->status]; @endphp
                    <li class="py-2 flex items-start justify-between gap-3">
                        <div>
                            <span class="font-medium">{{ $r->size() }}</span>
                            @if($r->comment)<span class="text-stone-500"> — {{ \Illuminate\Support\Str::limit($r->comment, 80) }}</span>@endif
                            @if($r->admin_note)<p class="text-xs text-stone-600 mt-0.5">↳ {{ $r->admin_note }}</p>@endif
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-xs bg-{{ $c }}-100 text-{{ $c }}-700">{{ __('template_requests.status.' . $r->status) }} · {{ $r->created_at->format('d.m') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($ordering)
        <div class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center px-4" wire:keydown.escape="$set('ordering', false)">
            <form wire:submit="sendOrder" class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('template_requests.order') }}</h2>
                    <p class="text-sm text-stone-500 mt-1">{{ __('template_requests.hint') }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs font-medium text-stone-600">{{ __('template_requests.width') }}
                        <input wire:model="order.width_mm" type="number" min="10" max="300" placeholder="58" class="{{ $field }} mt-1">
                        @error('order.width_mm')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs font-medium text-stone-600">{{ __('template_requests.height') }}
                        <input wire:model="order.height_mm" type="number" min="10" max="300" placeholder="40" class="{{ $field }} mt-1">
                        @error('order.height_mm')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs font-medium text-stone-600">{{ __('template_requests.columns') }}
                        <input wire:model="order.columns" type="number" min="1" max="10" class="{{ $field }} mt-1">
                        @error('order.columns')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs font-medium text-stone-600">{{ __('template_requests.rows') }}
                        <input wire:model="order.rows" type="number" min="1" max="50" placeholder="{{ __('template_requests.rows_placeholder') }}" class="{{ $field }} mt-1">
                        @error('order.rows')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-xs font-medium text-stone-600 col-span-2">{{ __('template_requests.gap') }}
                        <input wire:model="order.gap_mm" type="number" min="0" max="30" step="0.5" placeholder="2" class="{{ $field }} mt-1">
                    </label>
                </div>
                <label class="block text-xs font-medium text-stone-600">{{ __('template_requests.comment') }}
                    <textarea wire:model="order.comment" rows="3" maxlength="1000" placeholder="{{ __('template_requests.comment_placeholder') }}" class="{{ $field }} mt-1"></textarea>
                    @error('order.comment')<span class="text-rose-500 text-xs">{{ $message }}</span>@enderror
                </label>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" wire:click="$set('ordering', false)" class="px-4 py-2 text-sm text-stone-600">{{ __('common.cancel') }}</button>
                    <button type="submit" class="px-4 py-2 bg-amber-700 text-white rounded-lg text-sm font-medium hover:bg-amber-800">{{ __('template_requests.send') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse($templates as $t)
            <article class="bg-white border border-stone-200 rounded-xl p-4 flex flex-col">
                <div class="bg-stone-100 rounded-lg p-4 flex items-center justify-center h-52">
                    @include('partials.template-preview', ['template' => $t, 'previewHtml' => $previews[$t->id], 'maxW' => 260, 'maxH' => 170])
                </div>
                <div class="flex items-start justify-between gap-3 mt-3">
                    <div>
                        <h2 class="font-medium">{{ $t->name }}</h2>
                        <p class="text-xs text-stone-500 mt-0.5">
                            {{ $t->width_mm }}×{{ $t->height_mm }} mm
                            @if(($t->columns ?? 1) > 1) · {{ $t->columns }} {{ __('templates.per_row') }}, {{ __('templates.gap') }} {{ rtrim(rtrim(number_format($t->gap_mm, 1, '.', ''), '0'), '.') }}@endif
                        </p>
                    </div>
                    @if($t->is_default)
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">{{ __('templates.default') }}</span>
                    @endif
                </div>
                <a href="{{ route('templates.preview', $t) }}" target="_blank" class="text-xs text-amber-700 hover:underline mt-2">{{ __('templates.preview_pdf') }}</a>
            </article>
        @empty
            <p class="text-stone-400">{{ __('common.no_data') }}</p>
        @endforelse
    </div>
</div>
