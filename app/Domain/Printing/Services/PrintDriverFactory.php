<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Drivers\EzplDriver;
use App\Domain\Printing\Drivers\PrintDriverInterface;
use App\Domain\Printing\Drivers\ZplDriver;
use App\Domain\Printing\Models\Printer;

class PrintDriverFactory
{
    public static function make(Printer $printer): PrintDriverInterface
    {
        return match ($printer->type) {
            'zpl_zebra'   => new ZplDriver($printer->host, $printer->port),
            'ezpl_godex'  => new EzplDriver($printer->host, $printer->port),
            'sbpl_bixolon' => new EzplDriver($printer->host, $printer->port), // SBPL similar to EZPL
            default => throw new \Exception("Unknown printer type: {$printer->type}"),
        };
    }
}
