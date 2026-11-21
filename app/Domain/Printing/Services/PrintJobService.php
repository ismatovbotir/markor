<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Models\PrintJob;
use App\Jobs\RenderPrintJobJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class PrintJobService
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return PrintJob::with(['template', 'printer', 'creator'])
            ->when(Auth::user()->isClient(), fn ($q) => $q->where('created_by', Auth::id()))
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): PrintJob
    {
        $job = PrintJob::create([
            ...$data,
            'created_by' => Auth::id(),
            'status'     => 'queued',
            'quantity'   => count($data['code_ids']),
        ]);

        RenderPrintJobJob::dispatch($job);

        return $job;
    }
}
