<?php

namespace App\Domain\Printing\Drivers;

use App\Domain\Printing\Models\PrintJob;

interface PrintDriverInterface
{
    public function render(PrintJob $job, array $codes): string;
    public function send(string $output): bool;
}
