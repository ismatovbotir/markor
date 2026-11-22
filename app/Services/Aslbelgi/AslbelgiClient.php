<?php

namespace App\Services\Aslbelgi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

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

    /**
     * Detailed info of codes for their issuer / current owner (OPEN API §9.2, POST cod/private/codes).
     * Send identification codes only (01 + GTIN + 21 + serial), ≤ 1000 per request, already validated.
     * Auto-redirect is off: HTTP 307 means the key has business access to none of these codes.
     * 429, 5xx, connection errors and error code "1" are retried with backoff (at most 3 tries).
     *
     * @param list<string> $codes
     * @return array{ok: true, results: list<array>, forbidden: list<string>}
     *       | array{ok: false, reason: 'not_ours'|'key_invalid'|'no_rights'|'unreachable'|'rejected', status: int}
     */
    public function privateCodes(array $codes): array
    {
        $delays = (array) config('aslbelgi.check.retry_delays_ms', [1000, 3000, 7000]);
        $tries = max(1, min(3, count($delays)));

        for ($attempt = 1; ; $attempt++) {
            $response = null;
            try {
                $response = $this->http()->withoutRedirecting()->post('/public/api/cod/private/codes', [
                    'codes' => array_values($codes), 'addCodeHistory' => false, 'addChildCodes' => false,
                ]);
                $status = $response->status();
            } catch (ConnectionException) {
                $status = 0;
            }

            if ($status === 307) {
                return ['ok' => false, 'reason' => 'not_ours', 'status' => 307];
            }
            if ($response && $response->successful()) {
                return [
                    'ok' => true,
                    'results' => array_values(array_filter((array) ($response->json('results') ?? []), 'is_array')),
                    'forbidden' => array_values(array_filter((array) ($response->json('forbiddenCodes') ?? []), 'is_string')),
                ];
            }

            $retryable = $status === 0 || $status === 429 || $status >= 500 || ($response && self::errorCode($response) === '1');
            if ($retryable && $attempt < $tries) {
                Sleep::usleep(((int) ($delays[$attempt - 1] ?? 7000)) * 1000);
                continue;
            }

            if ($response) {
                self::logError('cod/private/codes', $response);
            }

            return ['ok' => false, 'status' => $status, 'reason' => match (true) {
                $status === 401 => 'key_invalid',
                $status === 403 => 'no_rights',
                $retryable      => 'unreachable',
                default         => 'rejected',
            }];
        }
    }

    /** Symbolic error code of an error body; the PDF's table and example differ, so read both shapes. */
    private static function errorCode(Response $response): ?string
    {
        $body = $response->json();
        if (!is_array($body)) {
            return null;
        }
        $first = array_is_list($body) ? ($body[0] ?? []) : $body;
        $code = is_array($first) ? ($first['code'] ?? $first['Code'] ?? $first['errorCode'] ?? null) : null;

        return is_scalar($code) ? (string) $code : null;
    }

    /** Only safe fields: the error body may echo the Authorization header (the key) in `context`. */
    private static function logError(string $method, Response $response): void
    {
        $body = $response->json();
        $first = is_array($body) ? (array_is_list($body) ? ($body[0] ?? []) : $body) : [];
        $pick = fn ($k) => is_array($first) && is_scalar($first[$k] ?? null) ? mb_substr((string) $first[$k], 0, 100) : null;

        Log::warning("Asl Belgisi $method failed", array_filter([
            'status' => $response->status(), 'code' => self::errorCode($response),
            'errorId' => $pick('errorId'), 'service' => $pick('service'),
        ], fn ($v) => $v !== null));
    }
}
