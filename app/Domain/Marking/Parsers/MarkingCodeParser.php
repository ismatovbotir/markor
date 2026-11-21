<?php

namespace App\Domain\Marking\Parsers;

use App\Domain\Catalog\Models\ProductGroup;

/**
 * Parses a GS1 DataMatrix marking code (text form, as it comes in the operator CSV or from a
 * scanner). The structures come from the product categories the super admin defines; the first
 * active category whose structure fits the code wins.
 */
class MarkingCodeParser
{
    /** @var array<int, array{id:int, structure:array}>|null */
    private ?array $groups = null;

    /**
     * Canonical stored form: control chars and scanner symbology prefix removed.
     */
    public function normalize(string $raw): string
    {
        $raw = trim($raw, " \t\n\r\0\x0B\xEF\xBB\xBF");
        if (str_starts_with($raw, ']d2') || str_starts_with($raw, ']d1') || str_starts_with($raw, ']C1')) {
            $raw = substr($raw, 3);
        }

        return str_replace(["\x1D", "\x1E", "\xE8"], '', $raw);
    }

    /**
     * @return array{code:string, gtin:string, serial:string, crypto_tail:string, product_group_id:int, fields:array<string,string>}
     *
     * @throws \InvalidArgumentException
     */
    public function parse(string $raw): array
    {
        $code = $this->normalize($raw);

        foreach ($this->groups() as $group) {
            $fields = self::split($code, $group['structure']);
            if ($fields === null) {
                continue;
            }

            return [
                'code'             => $code,
                'gtin'             => $fields['01'] ?? '',
                'serial'           => $fields['21'] ?? '',
                // everything after the serial: verification key + code (or the raw tail)
                'crypto_tail'      => implode('', array_diff_key($fields, ['01' => 1, '21' => 1])),
                'product_group_id' => $group['id'],
                'fields'           => $fields,
            ];
        }

        throw new \InvalidArgumentException('code does not match any product category structure (length ' . strlen($code) . ')');
    }

    /**
     * Splits a normalized code by a structure. Returns [ai => value] (raw tail under key "_"),
     * or null when the code doesn't fit.
     */
    public static function split(string $code, array $structure): ?array
    {
        if (!$structure || strlen($code) !== ProductGroup::lengthOf($structure)) {
            return null;
        }

        $pos = 0;
        $fields = [];
        foreach ($structure as $segment) {
            $ai = (string) ($segment['ai'] ?? '');
            if ($ai !== '' && substr($code, $pos, strlen($ai)) !== $ai) {
                return null;
            }
            $pos += strlen($ai);
            $fields[$ai === '' ? '_' : $ai] = substr($code, $pos, (int) $segment['length']);
            $pos += (int) $segment['length'];
        }

        if (isset($fields['01']) && !ctype_digit($fields['01'])) {
            return null;
        }

        return $fields;
    }

    /** The GS1 payload for the DataMatrix encoder: FNC1 + fields, GS after variable-length fields. */
    public static function gs1Payload(string $code, array $structure): ?string
    {
        $fields = self::split($code, $structure);
        if ($fields === null) {
            return null;
        }

        $payload = "\xE8";
        $last = count($structure) - 1;
        foreach (array_values($structure) as $i => $segment) {
            $ai = (string) ($segment['ai'] ?? '');
            $payload .= $ai . $fields[$ai === '' ? '_' : $ai];
            if (!empty($segment['variable']) && $i < $last) {
                $payload .= "\x1D";
            }
        }

        return $payload;
    }

    private function groups(): array
    {
        return $this->groups ??= ProductGroup::active()
            ->whereNotNull('structure')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'structure'])
            ->map(fn ($g) => ['id' => $g->id, 'structure' => $g->structure])
            ->all();
    }
}
