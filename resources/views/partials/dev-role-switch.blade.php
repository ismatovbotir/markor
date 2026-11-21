@if(config('app.dev_role_switch') && app()->isLocal())
    @php $current = auth()->user()?->isAdmin() ? 'admin' : (auth()->check() ? 'client' : null); @endphp
    <div data-dev-switch class="fixed bottom-3 left-1/2 -translate-x-1/2 z-50 flex items-center gap-1 rounded-full bg-stone-900/95 text-white text-xs shadow-lg pl-3 pr-1 py-1">
        <span class="text-stone-400 mr-1">DEV</span>
        @foreach(['admin' => __('users.roles.admin'), 'client' => __('dev.client')] as $role => $label)
            <a href="{{ route('dev.as', $role) }}"
               class="px-2.5 py-1 rounded-full transition {{ $current === $role ? 'bg-amber-500 text-stone-900 font-semibold' : 'hover:bg-white/10' }}">{{ $label }}</a>
        @endforeach
    </div>
@endif
