<?php

namespace App\Domain\Studio;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/** Reads the first sheet of an xlsx / ods / csv file: first row = column names. */
class SpreadsheetReader
{
    /**
     * @return array{headers: string[], rows: array<int, array<string,string>>, total: int}
     */
    public function read(string $path, string $extension, int $maxRows): array
    {
        $reader = match (strtolower($extension)) {
            'xlsx'  => new XlsxReader(),
            'ods'   => new OdsReader(),
            default => new CsvReader($this->csvOptions($path)),
        };

        $reader->open($path);

        $headers = [];
        $rows    = [];
        $total   = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($v) => $this->stringify($v), $row->toArray());

                    if (implode('', $cells) === '') {
                        continue;
                    }
                    if (!$headers) {
                        $headers = $this->headers($cells);
                        continue;
                    }

                    $total++;
                    if ($total > $maxRows) {
                        break 2; // we only use $maxRows rows: don't read the rest of a (possibly huge) sheet
                    }
                    if (count($rows) < $maxRows) {
                        $record = [];
                        foreach ($headers as $i => $header) {
                            $record[$header] = mb_substr($cells[$i] ?? '', 0, 500);
                        }
                        $rows[] = $record;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return ['headers' => $headers, 'rows' => $rows, 'total' => $total];
    }

    /** Column names are used as keys in the request, so characters with a special meaning there are dropped. */
    private function headers(array $cells): array
    {
        $headers = [];
        foreach (array_slice($cells, 0, 50) as $i => $cell) {
            $name = ltrim(trim(str_replace(['{', '}', '.', '*'], ' ', $cell)), '@ ') ?: 'Ustun ' . ($i + 1);
            $name = mb_substr($name, 0, 60);
            $base = $name;
            for ($n = 2; in_array($name, $headers, true); $n++) {
                $name = "{$base} {$n}";
            }
            $headers[] = $name;
        }

        return $headers;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'd.m.Y' : 'd.m.Y H:i'),
            $value instanceof \DateInterval      => $value->format('%H:%I:%S'),
            is_bool($value)                      => $value ? '1' : '0',
            is_float($value)                     => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            default                              => trim((string) $value),
        };
    }

    private function csvOptions(string $path): CsvOptions
    {
        $line = (string) fgets(fopen($path, 'r'));
        $best = ',';
        foreach ([';', "\t", '|'] as $delimiter) {
            if (substr_count($line, $delimiter) > substr_count($line, $best)) {
                $best = $delimiter;
            }
        }

        return new CsvOptions(FIELD_DELIMITER: $best);
    }
}
