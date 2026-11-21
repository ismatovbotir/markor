<?php

namespace App\Domain\Printing\Drivers;

use App\Domain\Printing\Models\PrintJob;

class EzplDriver implements PrintDriverInterface
{
    public function __construct(private string $host, private int $port = 9100) {}

    public function render(PrintJob $job, array $codes): string
    {
        $ezpl = "";

        foreach ($codes as $code) {
            $ezpl .= "^XA\n";
            $ezpl .= "~JSN\n";
            $ezpl .= "^PW" . ($job->template->width_mm * 8) . "\n";
            $ezpl .= "^LL" . ($job->template->height_mm * 8) . "\n";

            // Placeholder rendering
            $label = str_replace(
                ['{datamatrix}', '{gtin}', '{name}', '{sscc}', '{date}'],
                ['[DM]', $code->gtin, $code->product?->name_ru ?? '—', '—', now()->format('d.m.Y')],
                $job->template->template
            );

            $ezpl .= $label;
            $ezpl .= "^XZ\n";
        }

        return $ezpl;
    }

    public function send(string $ezpl): bool
    {
        try {
            $socket = fsockopen($this->host, $this->port, $errno, $errstr, 5);
            if (!$socket) {
                throw new \Exception("Connection failed: $errstr ($errno)");
            }

            fwrite($socket, $ezpl);
            fclose($socket);

            return true;
        } catch (\Throwable $e) {
            \Log::error("EZPL send failed: {$e->getMessage()}");
            return false;
        }
    }
}
