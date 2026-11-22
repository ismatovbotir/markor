<?php

namespace App\Jobs;

use App\Domain\Aslbelgi\AslbelgiCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs one "Asl Belgisi tekshiruvi" order. Only the order id is in the payload: the company key is read
 * (decrypted) when the job runs and never leaves the server except to the configured xtrace host.
 * If no worker picks it up, `aslbelgi:checks` (every minute) runs it; claiming is atomic.
 */
class RunAslbelgiCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $checkId) {}

    public function handle(AslbelgiCheckService $service): void
    {
        $service->run($this->checkId);
    }
}
