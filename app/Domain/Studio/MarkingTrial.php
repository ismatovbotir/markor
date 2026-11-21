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

    /**
     * The trial is per visitor and day, not per request: at most LIMIT different codes per IP per day
     * (printing the same codes again is free). Returns false when these codes would go over it.
     */
    public function allowToday(string $visitor, array $codes): bool
    {
        $key = 'marking-trial:' . sha1($visitor) . ':' . now()->toDateString();

        // locked: parallel requests can't each read "0 used" and print 10 more
        return \Illuminate\Support\Facades\Cache::lock($key . ':lock', 10)->block(5, function () use ($key, $codes) {
            $used = \Illuminate\Support\Facades\Cache::get($key, []);
            $all = array_values(array_unique([...$used, ...array_map('sha1', $codes)]));
            if (count($all) > self::LIMIT) {
                return false;
            }
            \Illuminate\Support\Facades\Cache::put($key, $all, now()->endOfDay());

            return true;
        });
    }

    /** Who the daily trial counts: an IPv4 address, or a whole IPv6 /64 (one home / phone network). */
    public static function visitorKey(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);

            return 'v6:' . bin2hex(substr($bin, 0, 8));
        }

        return 'v4:' . $ip;
    }

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
