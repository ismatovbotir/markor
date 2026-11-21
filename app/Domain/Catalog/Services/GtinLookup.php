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
    public const URL = 'https://tasnif.soliq.uz/api/cl-api/integration-mxik/get/information/';

    /**
     * @return array|null|false  the item, null when the classifier has no such barcode, false when the service failed
     */
    public function find(string $gtin): array|null|false
    {
        try {
            $response = Http::timeout(15)->acceptJson()->withUserAgent('Markor/1.0')
                ->get(self::URL, ['shtrixCode' => $gtin]);
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
        $product->update($values);

        return 'found';
    }

    /** "Алкоголсиз ичимликлар (...): COCA-COLA, 0.33L J/B" → "COCA-COLA, 0.33L J/B" (the part after the MXIK category). */
    public static function shortName(string $name): string
    {
        $pos = mb_strpos($name, ': ');

        return trim($pos !== false ? mb_substr($name, $pos + 2) : $name) ?: $name;
    }
}
