<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Product information by GTIN from the national classifier (tasnif.soliq.uz): name, MXIK code,
 * unit, Asl Belgisi flag. Open GET, no key. Results are kept in products.attributes; the lookup
 * status and time also in the indexed columns products.lookup_status / lookup_at.
 */
class GtinLookup
{
    /** Lookup address with a {gtin} placeholder (GTIN_LOOKUP_URL in .env, see config/catalog.php). */
    public static function url(string $gtin): string
    {
        return str_replace('{gtin}', rawurlencode($gtin), (string) config('catalog.gtin_lookup_url'));
    }

    /**
     * @return array|null|false  the item, null when the classifier has no such barcode, false when the service failed
     */
    public function find(string $gtin): array|null|false
    {
        try {
            $response = Http::timeout(15)->acceptJson()->withUserAgent('Markor/1.0')
                ->get(self::url($gtin));
        } catch (\Throwable $e) {
            Log::warning('GTIN lookup failed', ['gtin' => $gtin, 'error' => $e->getMessage()]);
            return false;
        }

        if (!$response->ok() || $response->json('success') !== true) {
            Log::warning('GTIN lookup error response', ['gtin' => $gtin, 'status' => $response->status()]);
            return false;
        }

        return $response->json('data.0') ?: null;
    }

    /**
     * The official name in another language (the information endpoint answers only in Uzbek Cyrillic).
     * Short form, without the MXIK category. null when not found or the service failed.
     */
    public function nameIn(string $gtin, string $lang = 'ru'): ?string
    {
        // the classifier stores 13-digit barcodes: a GTIN-14 with a leading 0 is the same EAN-13
        $barcode = strlen($gtin) === 14 && str_starts_with($gtin, '0') ? substr($gtin, 1) : $gtin;
        $url = str_replace(['{gtin}', '{lang}'], [rawurlencode($barcode), rawurlencode($lang)], (string) config('catalog.gtin_search_url'));
        try {
            $response = Http::timeout(15)->acceptJson()->withUserAgent('Markor/1.0')->get($url);
        } catch (\Throwable $e) {
            Log::warning('GTIN name lookup failed', ['gtin' => $gtin, 'lang' => $lang, 'error' => $e->getMessage()]);
            return null;
        }
        if (!$response->ok() || $response->json('success') !== true) {
            return null;
        }
        // search is a text search: take only the row with exactly this barcode
        $row = collect($response->json('data') ?? [])->first(fn ($r) => ltrim((string) ($r['internationalCode'] ?? ''), '0') === ltrim($barcode, '0'));
        $name = trim((string) ($row['name'] ?? ''));

        return $name !== '' ? self::shortName($name) : null;
    }

    /**
     * Fills a product from the classifier. The product name is only set when nobody has named it yet.
     * Returns the lookup status: found / not_found / error.
     */
    public function enrich(Product $product): string
    {
        $item = $this->find($product->gtin);
        $attributes = $product->attributes ?? [];
        $attributes['lookup_at'] = now()->toIso8601String();

        if ($item === false) {
            $attributes['lookup'] = 'error';
            $product->update(['attributes' => $attributes, 'lookup_status' => 'error', 'lookup_at' => now()]);
            return 'error';
        }

        if ($item === null) {
            $attributes['lookup'] = 'not_found';
            $product->update(['attributes' => $attributes, 'lookup_status' => 'not_found', 'lookup_at' => now()]);
            return 'not_found';
        }

        $package = $item['packages'][0] ?? [];
        $attributes = array_merge($attributes, [
            'lookup'          => 'found',
            'mxik'            => $item['mxik'] ?? null,
            'classifier_name' => $item['name'] ?? null,
            'classifier_name_ru' => $ru = $this->nameIn($product->gtin, 'ru'), // official Russian name
            'unit_uz'         => $package['nameUz'] ?? null,
            'unit_ru'         => $package['nameRu'] ?? null,
            'unit_lat'        => $package['nameLat'] ?? null,
            'aslbelgi_label'  => $item['label'] ?? null,     // owner's reading: Asl Belgisi marking flag
            'use_package'     => $item['usePackage'] ?? null, // owner's reading: aggregation into packages
        ]);

        $values = ['attributes' => $attributes, 'lookup_status' => 'found', 'lookup_at' => now()];
        if (blank($product->name_uz) && filled($item['name'] ?? null)) {
            $values['name_uz'] = self::shortName($item['name']);
        }
        if (blank($product->name_ru) && $ru) {
            $values['name_ru'] = mb_substr($ru, 0, 255);
        }
        $product->update($values);
        // clean name in Latin Uzbek + Russian (Claude, or transliteration when AI is unavailable)
        \App\Jobs\NameProduct::dispatch($product->id)->afterCommit();

        return 'found';
    }

    /** "Алкоголсиз ичимликлар (...): COCA-COLA, 0.33L J/B" → "COCA-COLA, 0.33L J/B" (the part after the MXIK category). */
    public static function shortName(string $name): string
    {
        $pos = mb_strpos($name, ': ');

        return trim($pos !== false ? mb_substr($name, $pos + 2) : $name) ?: $name;
    }
}
