<?php

namespace App\Domain\Companies;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Company details by tax number from an external service. Configure COMPANY_LOOKUP_URL with a
 * {tin} placeholder and COMPANY_LOOKUP_AUTHORIZATION with the full header value (e.g. "Basic …");
 * without the URL the lookup is off and
 * clients enter the details themselves. The field mapping below is generic — adjust it to the
 * real API's response once it is known.
 */
class CompanyLookup
{
    public function enabled(): bool
    {
        return filled(config('company.lookup_url'));
    }

    /** @return array{name:?string, legal_address:?string, director:?string, ...}|null */
    public function find(string $tin): ?array
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(10)->acceptJson()->withUserAgent('Markor/1.0')
                ->withHeaders(array_filter(['Authorization' => config('company.lookup_authorization')]))
                ->get(str_replace('{tin}', urlencode($tin), config('company.lookup_url')));
        } catch (\Throwable $e) {
            Log::warning('Company lookup failed', ['tin' => $tin, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $d = $response->json('data') ?? $response->json();
        if (!is_array($d)) {
            return null;
        }
        $pick = fn (array $keys) => collect($keys)->map(fn ($k) => data_get($d, $k))->first(fn ($v) => filled($v));

        $result = [
            // app.hippo.uz: short_name '"X" MCHJ', name = full legal name
            'name'          => $pick(['short_name', 'shortName', 'name', 'companyName', 'company_name', 'fullName']),
            'legal_address' => $pick(['address', 'legalAddress', 'legal_address']),
            'director'      => $pick(['director', 'directorName', 'director_name', 'head']),
            'phone'         => $pick(['phone', 'phoneNumber']),
            'email'         => $pick(['email']),
            'vat_code'      => $pick(['vat_reg_code', 'vatCode', 'vat_code', 'nds']),
            'raw'           => $d,
        ];

        // a company without a name, or whose tax number does not match, counts as not found
        if (blank($result['name']) || (isset($d['inn']) && (string) $d['inn'] !== $tin)) {
            return null;
        }

        return $result;
    }
}
