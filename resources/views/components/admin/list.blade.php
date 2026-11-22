{{-- The card of a list page. head: column labels in the fixed order (title, status, languages, updated, actions).
     Slots: default (list-row items or the empty-state), footer (count line).
     wide: a broader actions column for lists with reorder arrows. --}}
@props(['head' => [], 'wide' => false])
<div {{ $attributes->class('bg-white border border-stone-200 rounded-xl') }} @if($wide) style="--admin-actions: 230px" @endif>
    @if($head)
        <div class="admin-list-grid hidden md:grid px-4 py-2 border-b border-stone-200 bg-stone-50 rounded-t-xl text-xs font-medium text-stone-600">
            @foreach($head as $label)<span @class(['text-right' => $loop->last])>{{ $label }}</span>@endforeach
        </div>
    @endif
    <ul class="divide-y divide-stone-100">{{ $slot }}</ul>
    @isset($footer)<div class="px-4 py-2.5 border-t border-stone-200 text-xs text-stone-600">{{ $footer }}</div>@endisset
</div>
