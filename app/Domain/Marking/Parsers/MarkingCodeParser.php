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
    /** @var array<int, array{id:int, system:string, structure:array}>|null */
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
     * @return array{code:string, gtin:string, serial:string, crypto_tail:string, product_group_id:int, marking_system:string, fields:array<string,string>}
     *
     * @throws \InvalidArgumentException
     */
    public function parse(string $raw, ?string $preferSystem = null): array
    {
        $code = $this->normalize($raw);

        // the same structure may exist in several marking systems: the preferred system wins
        $groups = $this->groups();
        if ($preferSystem) {
            usort($groups, fn ($a, $b) => ($b['system'] === $preferSystem) <=> ($a['system'] === $preferSystem));
        }

        foreach ($groups as $group) {
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
                'marking_system'   => $group['system'],
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

    /** Marking systems whose categories fit the code ([] = not a marking code). */
    public function systemsOf(string $raw): array
    {
        $code = $this->normalize($raw);

        return collect($this->groups())
            ->filter(fn ($g) => self::split($code, $g['structure']) !== null)
            ->pluck('system')->unique()->values()->all();
    }

    /**
     * The marking system of a set of codes: decided by any code whose structure exists in one
     * system only. Returns [system|null, detected]: detected = false when every code fits several
     * systems (then $fallback is used), system = null when no code is a marking code.
     *
     * @param iterable<string> $raws
     * @return array{0: ?string, 1: bool}
     */
    public function detectSystem(iterable $raws, ?string $fallback = null): array
    {
        $votes = [];
        $hints = [];
        $any = false;
        foreach ($raws as $raw) {
            $systems = $this->systemsOf($raw);
            $any = $any || $systems !== [];
            if (count($systems) === 1) {
                $votes[$systems[0]] = ($votes[$systems[0]] ?? 0) + 1;
            } elseif (count($systems) > 1 && ($hint = self::gtinHint($this->normalize($raw))) && in_array($hint, $systems, true)) {
                $hints[$hint] = ($hints[$hint] ?? 0) + 1;
            }
        }
        if ($votes) {
            arsort($votes);

            return [array_key_first($votes), true];
        }
        if ($hints) {
            // the structure fits several systems; the GTIN's GS1 prefix breaks the tie (still switchable)
            arsort($hints);

            return [array_key_first($hints), false];
        }

        return [$any ? ($fallback ?: \App\Domain\Marking\Support\MarkingSystem::ASLBELGISI) : null, false];
    }

    /**
     * GS1 member prefix of the code's GTIN: 460–469 Russia, 478 Uzbekistan. Not proof of the market
     * (a GTIN keeps its prefix when exported), only a tie-breaker for structures several systems share.
     */
    public static function gtinHint(string $code): ?string
    {
        if (!preg_match('/^01\d(\d{3})/', $code, $m)) {
            return null; // GTIN-14 = indicator digit + GTIN-13; the prefix is its first 3 digits
        }
        $prefix = (int) $m[1];

        return match (true) {
            $prefix >= 460 && $prefix <= 469 => \App\Domain\Marking\Support\MarkingSystem::CHESTNY_ZNAK,
            $prefix === 478 => \App\Domain\Marking\Support\MarkingSystem::ASLBELGISI,
            default => null,
        };
    }

    private function groups(): array
    {
        return $this->groups ??= ProductGroup::active()
            ->whereNotNull('structure')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'marking_system', 'structure'])
            ->map(fn ($g) => ['id' => $g->id, 'system' => $g->marking_system ?: \App\Domain\Marking\Support\MarkingSystem::ASLBELGISI, 'structure' => $g->structure])
            ->all();
    }
}
