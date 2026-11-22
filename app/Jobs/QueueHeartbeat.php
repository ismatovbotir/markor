<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

/** Queued every minute by the scheduler; when a worker runs it, the admin "Tizim holati" page knows the worker is alive. */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Cache::forever(\App\Domain\System\SystemStatus::QUEUE_KEY, now()->toIso8601String());
    }
}
