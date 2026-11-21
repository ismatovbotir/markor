<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Marking\Models\ImportBatch;
use App\Domain\Stats\ServerResources;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super admin: company file queue, public site visits, server resources. */
#[Layout('layouts.app')]
class StatsPage extends Component
{
    public const TABS = ['queue', 'visits', 'resources'];
    public const ACTIVE = ['uploaded', 'pending', 'processing', 'checking'];

    #[Url]
    public string $tab = 'queue';

    #[Url]
    public int $days = 30;

    public function mount(): void
    {
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'queue';
        $this->days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
    }

    public function updatedDays(): void
    {
        $this->days = in_array($this->days, [7, 30, 90], true) ? $this->days : 30;
    }

    private function queue(): array
    {
        $counts = ImportBatch::query()->whereIn('status', self::ACTIVE)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $since = now()->subDay();

        return [
            'counts'    => collect(self::ACTIVE)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'locked'    => ImportBatch::where('is_locked', true)->count(),
            'done24'    => ImportBatch::where('status', 'completed')->where('processed_at', '>=', $since)->count(),
            'codes24'   => (int) ImportBatch::where('status', 'completed')->where('processed_at', '>=', $since)->sum('processed_rows'),
            'failed7'   => ImportBatch::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
            'pdfs24'    => DB::table('label_pdfs')->where('created_at', '>=', $since)->count(),
            'oldest'    => ImportBatch::whereIn('status', ['pending', 'processing', 'checking'])->min('created_at'),
            'jobs'      => DB::table('jobs')->count(),
            'failedJobs'=> DB::table('failed_jobs')->count(),
            'active'    => ImportBatch::with('company:id,name')->where(fn ($q) => $q->whereIn('status', self::ACTIVE)->orWhere('is_locked', true))
                ->orderBy('id')->limit(50)->get(['id', 'company_id', 'original_filename', 'status', 'is_locked', 'total_rows', 'processed_rows', 'source', 'created_at']),
            'failed'    => ImportBatch::with('company:id,name')->where('status', 'failed')->latest('updated_at')->limit(10)
                ->get(['id', 'company_id', 'original_filename', 'error_log', 'updated_at']),
            'perCompany'=> ImportBatch::query()->join('companies', 'companies.id', '=', 'import_batches.company_id')
                ->where('import_batches.created_at', '>=', now()->subDays(30))
                ->select('companies.name', DB::raw('count(*) as files'), DB::raw('sum(import_batches.processed_rows) as codes'))
                ->groupBy('companies.id', 'companies.name')->orderByDesc('codes')->limit(10)->get(),
        ];
    }

    private function visits(): array
    {
        $from = now()->subDays($this->days - 1)->toDateString();
        $rows = DB::table('site_visits')->where('date', '>=', $from);
        $daily = (clone $rows)->where('kind', 'page')->select('date', DB::raw('sum(count) as n'))->groupBy('date')->pluck('n', 'date');
        $uniques = DB::table('site_visitors')->where('date', '>=', $from)->select('date', DB::raw('count(*) as n'))->groupBy('date')->pluck('n', 'date');
        $series = collect(range($this->days - 1, 0))->map(function ($ago) use ($daily, $uniques) {
            $d = now()->subDays($ago)->toDateString();

            return ['date' => $d, 'views' => (int) ($daily[$d] ?? 0), 'visitors' => (int) ($uniques[$d] ?? 0)];
        });
        $top = fn (string $kind, int $limit = 10) => (clone $rows)->where('kind', $kind)->select('key', DB::raw('sum(count) as n'))
            ->groupBy('key')->orderByDesc('n')->limit($limit)->pluck('n', 'key');
        $today = now()->toDateString();

        return [
            'series'   => $series,
            'views'    => $series->sum('views'),
            'visitors' => $series->sum('visitors'),
            'today'    => ['views' => (int) ($daily[$today] ?? 0), 'visitors' => (int) ($uniques[$today] ?? 0)],
            'pages'    => $top('page', 15),
            'langs'    => $top('lang', 3),
            'refs'     => $top('ref'),
            'bots'     => $top('bot'),
        ];
    }

    public function render()
    {
        return view('livewire.admin.stats-page', [
            'queue'     => $this->tab === 'queue' ? $this->queue() : null,
            'visits'    => $this->tab === 'visits' ? $this->visits() : null,
            'resources' => $this->tab === 'resources' ? ServerResources::snapshot() : null,
        ]);
    }
}
