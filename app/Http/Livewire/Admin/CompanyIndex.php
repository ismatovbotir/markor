<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Companies\Company;
use App\Domain\Marking\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Super admin: client companies with users and usage (codes loaded = what billing charges for). */
#[Layout('layouts.app')]
class CompanyIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $view = 'companies'; // companies / no_company

    public function updatingSearch(): void { $this->resetPage(); }

    public function render()
    {
        $monthStart = Carbon::now()->startOfMonth();
        $batches = fn () => ImportBatch::withoutGlobalScope('owner')->where('status', 'completed');

        $usage = $batches()
            ->selectRaw('company_id, count(*) as batches, sum(processed_rows) as codes, max(processed_at) as last_upload')
            ->selectRaw('sum(case when processed_at >= ? then processed_rows else 0 end) as codes_month', [$monthStart])
            ->selectRaw('sum(case when processed_at >= ? then amount else 0 end) as amount_month', [$monthStart])
            ->groupBy('company_id');
        $people = User::selectRaw('company_id, count(*) as users')->whereNotNull('company_id')->groupBy('company_id');

        $companies = Company::query()
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('tin', 'like', "%$s%")->orWhere('director', 'like', "%$s%")))
            ->leftJoinSub($usage, 'usage', 'usage.company_id', '=', 'companies.id')
            ->leftJoinSub($people, 'people', 'people.company_id', '=', 'companies.id')
            ->select('companies.*', DB::raw('coalesce(people.users, 0) as users'), DB::raw('coalesce(usage.batches, 0) as batches'),
                DB::raw('coalesce(usage.codes, 0) as codes'), DB::raw('coalesce(usage.codes_month, 0) as codes_month'),
                DB::raw('coalesce(usage.amount_month, 0) as amount_month'), 'usage.last_upload')
            ->orderByDesc('codes_month')->orderByDesc('companies.created_at')
            ->paginate(25);

        $loners = $this->view === 'no_company'
            ? User::where('role', 'user')->whereNull('company_id')->latest()->get(['id', 'name', 'email', 'phone', 'is_active', 'created_at'])
            : collect();

        return view('livewire.admin.company-index', [
            'loners' => $loners,
            'companies' => $companies,
            'totals' => [
                'companies'   => Company::count(),
                'new_month'   => Company::where('created_at', '>=', $monthStart)->count(),
                'no_company'  => User::where('role', 'user')->whereNull('company_id')->count(),
                'codes_month' => (int) $batches()->where('processed_at', '>=', $monthStart)->sum('processed_rows'),
                'codes'       => (int) $batches()->sum('processed_rows'),
                'amount_month'=> (float) $batches()->where('processed_at', '>=', $monthStart)->sum('amount'),
            ],
        ]);
    }
}
