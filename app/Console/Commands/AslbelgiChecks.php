<?php

namespace App\Console\Commands;

use App\Domain\Aslbelgi\AslbelgiCheckService;
use Illuminate\Console\Command;

/** Every minute: runs Asl Belgisi checks no queue worker took, and settles checks that died while running. */
class AslbelgiChecks extends Command
{
    protected $signature = 'aslbelgi:checks';
    protected $description = 'Run waiting Asl Belgisi batch checks and settle stuck ones';

    public function handle(AslbelgiCheckService $service): int
    {
        $this->line('Handled ' . $service->sweep() . ' check(s)');

        return self::SUCCESS;
    }
}
