<?php

namespace App\Domain\Aggregation;

use Illuminate\Support\Str;

class SsccPoolGenerator
{
    /**
     * GS1 SSCC format: (00) + GCP(varies) + Serial(varies) + Check digit
     * For testing: 00 + 123456789 + random(8) + check
     * Production: customer's GCP prefix used
     */
    public function generate(string $gcp = '123456789', int $count = 1000): array
    {
        $pool = [];
        $usedSerials = $this->loadUsedSerials();

        for ($i = 0; $i < $count; $i++) {
            do {
                $serial = str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
            } while (isset($usedSerials[$serial]));

            $sscc = $this->buildSscc($gcp, $serial);
            $pool[] = [
                'sscc'         => $sscc,
                'sscc_source'  => 'generated',
                'gcp'          => $gcp,
                'serial'       => $serial,
                'check_digit'  => substr($sscc, -1),
                'is_used'      => false,
                'created_at'   => now(),
            ];

            $usedSerials[$serial] = true;
        }

        return $pool;
    }

    public function importFromCsv(string $csvPath): array
    {
        $pool = [];
        if (!file_exists($csvPath)) {
            return $pool;
        }

        $handle = fopen($csvPath, 'r');
        while (($row = fgetcsv($handle)) !== false) {
            $sscc = trim($row[0] ?? '');
            if (strlen($sscc) === 18 && is_numeric($sscc)) {
                $pool[] = [
                    'sscc'         => $sscc,
                    'sscc_source'  => 'imported_pool',
                    'gcp'          => substr($sscc, 2, 9),
                    'serial'       => substr($sscc, 11, 6),
                    'check_digit'  => substr($sscc, -1),
                    'is_used'      => false,
                    'created_at'   => now(),
                ];
            }
        }
        fclose($handle);

        return $pool;
    }

    private function buildSscc(string $gcp, string $serial): string
    {
        $payload = $gcp . $serial; // 9 + 8 = 17 chars
        $checkDigit = $this->calculateCheckDigit($payload);
        return '00' . $payload . $checkDigit;
    }

    private function calculateCheckDigit(string $payload): string
    {
        $odd = 0;
        $even = 0;

        for ($i = 0; $i < strlen($payload); $i++) {
            if (($i + 1) % 2 === 1) {
                $odd += (int) $payload[$i];
            } else {
                $even += (int) $payload[$i];
            }
        }

        $total = ($odd * 3) + $even;
        $check = (10 - ($total % 10)) % 10;

        return (string) $check;
    }

    private function loadUsedSerials(): array
    {
        // TODO: Load from DB to avoid duplicates
        return [];
    }
}
