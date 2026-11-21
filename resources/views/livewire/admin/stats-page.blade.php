@php
    use App\Domain\Stats\ServerResources as R;
    $card = 'bg-white border border-stone-200 rounded-xl p-4';
@endphp
<div @if($tab === 'queue') wire:poll.10s @endif>
    <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
        <div>
            <h1 class="text-xl font-semibold">{{ __('nav.stats') }}</h1>
            <p class="text-sm text-stone-500 mt-1">{{ __('stats.hint') }}</p>
        </div>
        <div class="inline-flex rounded-lg border border-stone-200 bg-white p-1 text-sm">
            @foreach(\App\Http\Livewire\Admin\StatsPage::TABS as $t)
                <button wire:click="$set('tab', '{{ $t }}')" class="px-3 py-1.5 rounded-md {{ $tab === $t ? 'bg-amber-100 text-amber-900 font-medium' : 'text-stone-600 hover:text-stone-900' }}">{{ __('stats.tabs.' . $t) }}</button>
            @endforeach
        </div>
    </div>

    @if($queue)
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($queue['counts'] as $status => $n)
                <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.status.' . $status) }}</p><p class="text-2xl font-semibold mt-1 {{ $n ? 'text-amber-700' : 'text-stone-900' }}">{{ $n }}</p></div>
            @endforeach
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.locked') }}</p><p class="text-2xl font-semibold mt-1 {{ $queue['locked'] ? 'text-rose-600' : '' }}">{{ $queue['locked'] }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.done24') }}</p><p class="text-2xl font-semibold mt-1 text-emerald-600">{{ $queue['done24'] }}</p><p class="text-xs text-stone-500">{{ number_format($queue['codes24'], 0, '.', ' ') }} {{ __('stats.codes') }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.pdfs24') }}</p><p class="text-2xl font-semibold mt-1">{{ $queue['pdfs24'] }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.failed7') }}</p><p class="text-2xl font-semibold mt-1 {{ $queue['failed7'] ? 'text-rose-600' : '' }}">{{ $queue['failed7'] }}</p></div>
        </div>
        <p class="text-xs text-stone-500 mt-3">
            {{ __('stats.jobs', ['n' => $queue['jobs']]) }} · {{ __('stats.failed_jobs', ['n' => $queue['failedJobs']]) }}
            @if($queue['oldest']) · <span class="text-amber-700">{{ __('stats.oldest', ['ago' => \Illuminate\Support\Carbon::parse($queue['oldest'])->diffForHumans()]) }}</span>@endif
            · {{ __('stats.auto_refresh') }}
        </p>

        <div class="grid lg:grid-cols-2 gap-4 mt-5">
            <div class="{{ $card }} lg:col-span-2">
                <h2 class="font-semibold text-sm mb-3">{{ __('stats.active_files') }}</h2>
                @if($queue['active']->isEmpty())
                    <p class="text-sm text-stone-500">{{ __('stats.queue_empty') }}</p>
                @else
                    <div class="overflow-x-auto"><table class="w-full text-sm">
                        <thead class="text-xs text-stone-500 text-left"><tr><th class="py-1 pr-3">#</th><th class="pr-3">{{ __('stats.company') }}</th><th class="pr-3">{{ __('stats.file') }}</th><th class="pr-3">{{ __('stats.state') }}</th><th class="pr-3 text-right">{{ __('stats.rows') }}</th><th class="text-right">{{ __('stats.since') }}</th></tr></thead>
                        <tbody class="divide-y divide-stone-100">
                        @foreach($queue['active'] as $b)
                            <tr>
                                <td class="py-1.5 pr-3 text-stone-400">{{ $b->id }}</td>
                                <td class="pr-3">@if($b->company)<a href="{{ route('admin.companies.show', $b->company_id) }}" class="hover:text-amber-700">{{ $b->company->name }}</a>@else — @endif</td>
                                <td class="pr-3 truncate max-w-[16rem]" title="{{ $b->original_filename }}">{{ $b->original_filename }} @if($b->source === 'telegram')<span class="text-[10px] text-sky-600">TG</span>@endif</td>
                                <td class="pr-3">@if($b->is_locked)<span class="text-rose-600">{{ __('stats.locked_short') }}</span>@else<span class="text-amber-700">{{ __('stats.status.' . $b->status) }}</span>@endif</td>
                                <td class="pr-3 text-right font-mono text-xs">{{ number_format($b->processed_rows, 0, '.', ' ') }}@if($b->total_rows) / {{ number_format($b->total_rows, 0, '.', ' ') }}@endif</td>
                                <td class="text-right text-xs text-stone-500">{{ $b->created_at->diffForHumans(null, true) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
            <div class="{{ $card }}">
                <h2 class="font-semibold text-sm mb-3">{{ __('stats.per_company') }}</h2>
                @forelse($queue['perCompany'] as $c)
                    <div class="flex justify-between text-sm py-1"><span class="truncate">{{ $c->name }}</span><span class="font-mono text-xs text-stone-600">{{ $c->files }} · {{ number_format((int) $c->codes, 0, '.', ' ') }}</span></div>
                @empty<p class="text-sm text-stone-500">—</p>@endforelse
            </div>
            <div class="{{ $card }}">
                <h2 class="font-semibold text-sm mb-3">{{ __('stats.recent_failed') }}</h2>
                @forelse($queue['failed'] as $f)
                    <div class="text-sm py-1"><span class="text-stone-400">#{{ $f->id }}</span> {{ $f->company?->name }} — <span class="text-stone-600">{{ \Illuminate\Support\Str::limit($f->original_filename, 40) }}</span>
                        <span class="block text-xs text-rose-600">{{ \Illuminate\Support\Str::limit(collect($f->error_log)->first(), 90) }} · {{ $f->updated_at->diffForHumans() }}</span></div>
                @empty<p class="text-sm text-stone-500">—</p>@endforelse
            </div>
        </div>
    @endif

    @if($visits)
        @php $max = max(1, $visits['series']->max('views')); @endphp
        <div class="flex items-center gap-2 mb-4 text-sm">
            @foreach([7, 30, 90] as $d)
                <button wire:click="$set('days', {{ $d }})" class="px-3 py-1 rounded-md border {{ $days === $d ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-stone-200 bg-white text-stone-600' }}">{{ __('stats.days', ['n' => $d]) }}</button>
            @endforeach
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.views_today') }}</p><p class="text-2xl font-semibold mt-1">{{ $visits['today']['views'] }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.visitors_today') }}</p><p class="text-2xl font-semibold mt-1">{{ $visits['today']['visitors'] }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.views_period', ['n' => $days]) }}</p><p class="text-2xl font-semibold mt-1">{{ number_format($visits['views'], 0, '.', ' ') }}</p></div>
            <div class="{{ $card }}"><p class="text-xs text-stone-500">{{ __('stats.visitors_period', ['n' => $days]) }}</p><p class="text-2xl font-semibold mt-1">{{ number_format($visits['visitors'], 0, '.', ' ') }}</p></div>
        </div>
        <div class="{{ $card }} mt-4">
            <div class="flex items-center justify-between mb-3"><h2 class="font-semibold text-sm">{{ __('stats.chart') }}</h2>
                <span class="flex items-center gap-3 text-xs text-stone-500"><span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-amber-600"></span>{{ __('stats.views') }}</span><span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-stone-800"></span>{{ __('stats.visitors') }}</span></span></div>
            <div class="flex items-end gap-px h-40">
                @foreach($visits['series'] as $p)
                    <div class="flex-1 h-full flex items-end gap-px group relative" title="{{ \Illuminate\Support\Carbon::parse($p['date'])->format('d.m') }}: {{ $p['views'] }} / {{ $p['visitors'] }}">
                        <div class="flex-1 bg-amber-600/80 rounded-t-sm" style="height: {{ round($p['views'] / $max * 100, 1) }}%"></div>
                        <div class="flex-1 bg-stone-800/80 rounded-t-sm" style="height: {{ round($p['visitors'] / $max * 100, 1) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-stone-400 mt-1"><span>{{ \Illuminate\Support\Carbon::parse($visits['series']->first()['date'])->format('d.m') }}</span><span>{{ \Illuminate\Support\Carbon::parse($visits['series']->last()['date'])->format('d.m') }}</span></div>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            @foreach(['pages' => 'stats.top_pages', 'refs' => 'stats.top_refs', 'langs' => 'stats.langs', 'bots' => 'stats.bots'] as $key => $label)
                <div class="{{ $card }} {{ $key === 'pages' ? 'md:col-span-2' : '' }}">
                    <h2 class="font-semibold text-sm mb-3">{{ __($label) }}</h2>
                    @forelse($visits[$key] as $name => $n)
                        <div class="flex justify-between gap-3 text-sm py-0.5"><span class="truncate {{ $key === 'pages' ? 'font-mono text-xs' : '' }}">{{ $key === 'langs' ? strtoupper($name) : $name }}</span><span class="font-mono text-xs text-stone-600">{{ number_format($n, 0, '.', ' ') }}</span></div>
                    @empty<p class="text-sm text-stone-500">—</p>@endforelse
                </div>
            @endforeach
        </div>
        <p class="text-xs text-stone-500 mt-3">{{ __('stats.visits_note') }}</p>
    @endif

    @if($resources)
        @php $r = $resources; @endphp
        <div class="grid md:grid-cols-3 gap-4">
            <div class="{{ $card }}">
                <h2 class="font-semibold text-sm">{{ __('stats.disk') }}</h2>
                <p class="text-2xl font-semibold mt-2">{{ R::bytes($r['disk_free']) }} <span class="text-sm font-normal text-stone-500">{{ __('stats.free_of', ['total' => R::bytes($r['disk_total'])]) }}</span></p>
                @if($r['disk_used_percent'] !== null)
                    <div class="h-2 rounded-full bg-stone-100 mt-3 overflow-hidden"><div class="h-full {{ $r['disk_used_percent'] > 85 ? 'bg-rose-600' : 'bg-amber-600' }}" style="width: {{ $r['disk_used_percent'] }}%"></div></div>
                    <p class="text-xs text-stone-500 mt-1">{{ __('stats.used', ['p' => $r['disk_used_percent']]) }}</p>
                @endif
                <div class="mt-3 space-y-1">
                    @foreach($r['folders'] as $dir => $size)<div class="flex justify-between text-sm"><span class="font-mono text-xs">{{ $dir }}</span><span class="text-stone-600">{{ R::bytes($size) }}</span></div>@endforeach
                </div>
            </div>
            <div class="{{ $card }}">
                <h2 class="font-semibold text-sm">{{ __('stats.database') }} <span class="text-xs font-normal text-stone-400">{{ $r['db_driver'] }}</span></h2>
                <p class="text-2xl font-semibold mt-2">{{ R::bytes($r['database']['size']) }}</p>
                <div class="mt-3 space-y-1">
                    @foreach($r['database']['tables'] as $t)
                        <div class="flex justify-between gap-2 text-sm"><span class="font-mono text-xs truncate">{{ $t['name'] }}</span><span class="text-xs text-stone-600 whitespace-nowrap">{{ number_format((int) $t['rows'], 0, '.', ' ') }} @if($t['size'])· {{ R::bytes($t['size']) }}@endif</span></div>
                    @endforeach
                </div>
            </div>
            <div class="{{ $card }}">
                <h2 class="font-semibold text-sm">{{ __('stats.server') }}</h2>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-500">{{ __('stats.load') }}</dt><dd class="font-mono text-xs">{{ $r['load'] ? implode(' · ', array_map(fn ($v) => number_format($v, 2), $r['load'])) : '—' }}@if($r['cpus']) <span class="text-stone-400">/ {{ $r['cpus'] }} CPU</span>@endif</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">{{ __('stats.memory') }}</dt><dd class="font-mono text-xs">@if($r['memory']){{ R::bytes($r['memory']['available']) }} {{ __('stats.free_of', ['total' => R::bytes($r['memory']['total'])]) }}@else — @endif</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">{{ __('stats.queue_jobs') }}</dt><dd class="font-mono text-xs">{{ $r['queue']['jobs'] ?? '—' }}@if($r['queue']['oldest']) <span class="text-amber-700">({{ \Carbon\CarbonInterval::seconds($r['queue']['oldest'])->cascade()->forHumans(short: true) }})</span>@endif</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">{{ __('stats.failed_jobs_short') }}</dt><dd class="font-mono text-xs {{ $r['queue']['failed'] ? 'text-rose-600' : '' }}">{{ $r['queue']['failed'] ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-stone-500">PHP / Laravel</dt><dd class="font-mono text-xs">{{ $r['php'] }} / {{ $r['laravel'] }}</dd></div>
                </dl>
            </div>
        </div>
        <p class="text-xs text-stone-500 mt-3">{{ __('stats.resources_note') }}</p>
    @endif
</div>
