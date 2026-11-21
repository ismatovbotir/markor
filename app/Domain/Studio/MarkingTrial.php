<?php

namespace App\Domain\Studio;

use App\Domain\Catalog\Models\ProductGroup;
use App\Domain\Marking\Parsers\MarkingCodeParser;
use App\Domain\Printing\Renderers\DataMatrixRenderer;

/**
 * Free trial of real Asl Belgisi marks in the public designer: codes in an uploaded file are
 * recognised by the product category structures, and up to LIMIT of them can be printed as
 * proper GS1 DataMatrix (FNC1 + GS separators). More codes → the Asl Belgisi section (login).
 * Nothing is stored and codes are not checked for uniqueness here.
 */
class MarkingTrial
{
    public const LIMIT = 10;

    /** Pseudo symbology of the designer's "Asl Belgisi DataMatrix" element. */
    public const SYMBOLOGY = 'asl';

    public function __construct(private MarkingCodeParser $parser, private DataMatrixRenderer $renderer) {}

    /**
     * Which column holds marking codes (most values parse), and whether the "header" is a code too.
     *
     * @return array{column:string, header_is_code:bool, count:int}|null
     */
    public function detect(array $headers, array $rows): ?array
    {
        foreach ($headers as $header) {
            $values = array_filter(array_map(fn ($r) => (string) ($r[$header] ?? ''), array_slice($rows, 0, 50)), 'strlen');
            if (!$values) {
                continue;
            }
            $ok = count(array_filter($values, fn ($v) => $this->isCode($v)));
            if ($ok / count($values) >= 0.8) {
                return ['column' => $header, 'header_is_code' => $this->isCode((string) $header), 'count' => count($rows)];
            }
        }

        return null;
    }

    public function isCode(string $value): bool
    {
        try {
            $this->parser->parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * GS1 DataMatrix PNG (data URI) of a marking code.
     *
     * @throws \InvalidArgumentException when the value is not a known marking code
     */
    public function png(string $value): string
    {
        $parsed = $this->parser->parse($value);
        $structure = ProductGroup::find($parsed['product_group_id'])?->structure;
        $payload = $structure ? MarkingCodeParser::gs1Payload($parsed['code'], $structure) : null;
        if (!$payload) {
            throw new \InvalidArgumentException('no GS1 structure');
        }

        return $this->renderer->pngFromPayload($payload);
    }
}
