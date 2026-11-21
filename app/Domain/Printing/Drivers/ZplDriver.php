<?php

namespace App\Domain\Printing\Drivers;

use App\Domain\Marking\Models\MarkingCode;
use App\Domain\Printing\Models\PrintJob;

class ZplDriver implements PrintDriverInterface
{
    public function __construct(private string $host, private int $port = 9100) {}

    public function render(PrintJob $job, array $codes): string
    {
        $zpl = "^XA\n";
        $zpl .= "^MMT\n";
        $zpl .= "^PW" . ($job->template->width_mm * 8) . "\n";
        $zpl .= "^LL" . ($job->template->height_mm * 8) . "\n";
        $zpl .= "^LS0\n";

        foreach ($codes as $code) {
            // Parse template placeholders
            $label = str_replace(
                ['{datamatrix}', '{gtin}', '{name}', '{sscc}', '{date}'],
                ['[DM]', $code->gtin, $code->product?->name_ru ?? '—', '—', now()->format('d.m.Y')],
                $job->template->template
            );

            $zpl .= $label;
        }

        $zpl .= "^XZ\n";
        return $zpl;
    }

    public function send(string $zpl): bool
    {
        try {
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
            if (!$socket) {
                throw new \Exception("Connection failed: $errstr ($errno)");
            }

            fwrite($socket, $zpl);
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            \Log::error("ZPL send failed: {$e->getMessage()}");
            return false;
        }
    }
}
