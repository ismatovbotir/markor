<?php

namespace App\Http\Livewire\Admin;

use App\Domain\System\SystemStatus;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Super admin: "Tizim holati" — scheduler, queue worker, waiting and failed jobs. */
#[Layout('layouts.app')]
class SystemPage extends Component
{
    public ?string $detail = null;   // uuid of the failed job shown in the details window

    public function showDetail(string $uuid): void
    {
        $this->detail = $uuid;
    }

    public function closeDetail(): void
    {
        $this->detail = null;
    }

    public function retry(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        \App\Domain\Reporting\AuditLog::record('system.job_retried', 'failed_job', 0, ['uuid' => $uuid]);
        $this->dispatch('notify', type: 'success', message: __('system.retried'));
    }

    public function retryAll(): void
    {
        Artisan::call('queue:retry', ['id' => ['all']]);
        \App\Domain\Reporting\AuditLog::record('system.job_retried', 'failed_job', 0, ['uuid' => 'all']);
        $this->dispatch('notify', type: 'success', message: __('system.retried'));
    }

    public function forget(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->dispatch('notify', type: 'success', message: __('common.deleted'));
    }

    public function render(SystemStatus $status)
    {
        return view('livewire.admin.system-page', [
            's' => $status->snapshot(),
            'd' => $this->detail ? $status->failedDetail($this->detail) : null,
        ]);
    }
}
