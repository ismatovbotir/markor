<?php

namespace App\Services\Aslbelgi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Read-only client for the Asl Belgisi OPEN API, authorised with one company's business-user API key.
 * Never log or return the key; it is sent only to config('aslbelgi.base_url').
 */
class AslbelgiClient
{
    public function __construct(private readonly string $apiKey) {}

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('aslbelgi.base_url'), '/'))
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout(config('aslbelgi.timeout', 15));
    }

    /**
     * Does the key belong to the participant with this TIN / PINFL? (limit: 100 requests a day)
     * @return array{ok: bool, belongs?: bool, expires_on?: ?string, status?: int}
     */
    public function checkKey(string $tin): array
    {
        try {
            $response = $this->http()->get('/public/api/v1/party/parties/' . rawurlencode($tin) . '/api-keys/check');
        } catch (ConnectionException) {
            return ['ok' => false, 'status' => 0];
        }

        if (!$response->successful()) {
            return ['ok' => false, 'status' => $response->status()];
        }

        return ['ok' => true, 'belongs' => (bool) $response->json('isTinCorrect'), 'expires_on' => $response->json('expiresOn')];
    }
}
