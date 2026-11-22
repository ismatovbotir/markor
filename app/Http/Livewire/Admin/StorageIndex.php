<?php

namespace App\Http\Livewire\Admin;

use App\Domain\Companies\Company;
use App\Domain\Reporting\CodeStorage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super admin: "Kodlar ombori" — codes and storage per company (numbers only, never code values). */
#[Layout('layouts.app')]
class StorageIndex extends Component
{
    public const SORTS = ['total', 'db_bytes', 'pdf_bytes', 'last_upload'];

    #[Url(except: '')] public string $search = '';
    #[Url(except: 'total')] public string $sort = 'total';

    public function render(CodeStorage $storage)
    {
        $stats = $storage->perCompany();
        $q = mb_strtolower(trim($this->search));
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'total';
        $rows = Company::orderBy('name')->get(['id', 'name', 'tin'])
            ->filter(fn ($c) => $q === '' || str_contains(mb_strtolower($c->name), $q) || str_contains((string) $c->tin, $q))
            ->map(fn ($c) => ['company' => $c] + ($stats[$c->id] ?? ['total' => 0, 'imported' => 0, 'printed' => 0, 'cancelled' => 0, 'problems' => 0, 'batches' => 0,
                'first_batch' => null, 'last_upload' => null, 'last_print' => null, 'db_bytes' => 0, 'pdf_bytes' => 0, 'pdf_cached' => 0, 'pdf_archived' => 0]))
            ->sortByDesc(fn ($r) => $sort === 'last_upload' ? (string) $r['last_upload'] : $r[$sort])->values();

        return view('livewire.admin.storage-index', ['rows' => $rows, 'summary' => $storage->summary(), 'activeSort' => $sort]);
    }
}
