<div>
    @php $field = 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white'; @endphp
    <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.templates') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('templates.catalog_hint') }}</p>
        </div>
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

    {{-- label size filter (both blocks) --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-4 text-sm" role="search">
        <div class="shrink-0 flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-xs text-stone-600">{{ __('templates.filter_width') }}
                <input wire:model.live.debounce.400ms="width" type="number" inputmode="numeric" min="1" max="300" placeholder="58"
                       class="w-20 border border-stone-300 rounded-lg px-2 py-1.5 text-sm font-mono bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
            </label>
            <span class="text-stone-400">×</span>
            <label class="flex items-center gap-1.5 text-xs text-stone-600">{{ __('templates.filter_height') }}
                <input wire:model.live.debounce.400ms="height" type="number" inputmode="numeric" min="1" max="300" placeholder="40"
                       class="w-20 border border-stone-300 rounded-lg px-2 py-1.5 text-sm font-mono bg-white focus:outline-none focus:ring-2 focus:ring-amber-400">
            </label>
            <span class="text-xs text-stone-500">mm · ±{{ \App\Http\Livewire\Printing\TemplateCatalog::SIZE_TOLERANCE_MM }}</span>
            @if($width !== '' || $height !== '')
                <button type="button" wire:click="clearSize" class="text-xs text-stone-500 hover:text-amber-700 underline">{{ __('templates.filter_clear') }}</button>
            @endif
        </div>
    </div>

    @if(auth()->user()->company_id)
        {{-- Block 1. Mine: own designs ("Мой") + global templates the company starred ("Markor") --}}
        <section class="mb-8" aria-labelledby="mine-title">
            <div class="flex items-baseline justify-between gap-3 mb-1 flex-wrap">
                <h2 id="mine-title" class="text-lg font-semibold">{{ __('templates.block_mine') }}</h2>
                <span class="text-xs text-stone-500 tabular-nums">{{ __('templates.slots', ['used' => $limit - $slotsLeft, 'limit' => $limit]) }}</span>
            </div>
            <p class="text-xs text-stone-500 mb-3">{{ __('templates.block_mine_hint') }}</p>
            <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                {{-- draw your own (up to the company's limit) --}}
                @if($slotsLeft > 0)
                    <a href="{{ route('templates.mine', ['new' => 1]) }}"
                       class="bg-white border-2 border-dashed border-amber-300 rounded-xl p-4 flex flex-col hover:border-amber-400 hover:bg-amber-50 transition focus:outline-none focus:ring-2 focus:ring-amber-400">
                        <span class="bg-amber-50 rounded-lg h-52 w-full flex flex-col items-center justify-center gap-3">
                            <span class="w-12 h-12 rounded-full bg-amber-700 text-white flex items-center justify-center">@svg('heroicon-o-pencil-square', 'w-6 h-6')</span>
                        </span>
                        <span class="font-medium mt-3">{{ __('templates.mine_new') }}</span>
                        <span class="text-xs text-stone-500 mt-0.5">{{ __('templates.mine_new_hint', ['left' => $slotsLeft]) }}</span>
                    </a>
                @endif
                {{-- or order one from Markor --}}
                <button type="button" wire:click="openOrder"
                        class="bg-white border-2 border-dashed border-stone-300 rounded-xl p-4 flex flex-col text-left hover:border-amber-400 hover:bg-amber-50 transition focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <span class="bg-stone-50 rounded-lg h-52 w-full flex flex-col items-center justify-center gap-3">
                        <span class="w-12 h-12 rounded-full bg-stone-700 text-white flex items-center justify-center">@svg('heroicon-o-plus', 'w-6 h-6')</span>
                        <span class="text-xs font-mono text-stone-600">? × ? mm</span>
                    </span>
                    <span class="font-medium mt-3">{{ __('template_requests.order') }}</span>
                    <span class="text-xs text-stone-500 mt-0.5">{{ __('template_requests.hint') }}</span>
                </button>
                @foreach($mineList as $t)
                    @include('livewire.printing.partials.catalog-card', ['t' => $t, 'block' => 'mine'])
                @endforeach
            </div>
            @if($mineList->isEmpty())
                <p class="text-sm text-stone-500 mt-3">{{ $filtering ? __('templates.nothing_found') : __('templates.block_mine_empty') }}</p>
            @endif
        </section>
    @endif

    {{-- Block 2. Global templates (made by Markor, or published client designs) --}}
    <section aria-labelledby="global-title">
        <div class="flex items-baseline justify-between gap-3 mb-1 flex-wrap">
            <h2 id="global-title" class="text-lg font-semibold">{{ __('templates.block_global') }}</h2>
            <span class="text-xs text-stone-500 tabular-nums">{{ $global->count() }} / {{ $total }}</span>
        </div>
        <p class="text-xs text-stone-500 mb-3">{{ __('templates.block_global_hint') }}</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
            @forelse($global as $t)
                @include('livewire.printing.partials.catalog-card', ['t' => $t, 'block' => 'global'])
            @empty
                <p class="text-stone-500 text-sm self-center">{{ $filtering ? __('templates.nothing_found') : __('common.no_data') }}</p>
            @endforelse
        </div>
    </section>
</div>
