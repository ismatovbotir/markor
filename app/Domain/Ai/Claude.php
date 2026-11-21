<?php

namespace App\Domain\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\PaymentRequiredException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\ContractSpendLimitReachedException;
use Anthropic\EnforcedSpendLimitReachedException;
use Anthropic\LinkedOrganizationSpendLimitReachedException;
use App\Domain\Telegram\Notifier;
use App\Domain\Telegram\TelegramApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around the Claude API for Markor's features. It never throws to the caller:
 * on any problem it returns null and the feature uses its own fallback.
 *
 *  - no key / AI_ENABLED=false / monthly cap reached → null without calling the API;
 *  - credit finished or spend limit reached (402, "credit balance is too low", spend-limit 429s) and
 *    a wrong key (401/403) → calls are paused for AI_PAUSE_MINUTES and the super admin gets ONE
 *    Telegram alert per pause;
 *  - rate limit / overloaded / network → null (the queue job retries later);
 *  - a safety refusal → null (server-side fallbacks first try another model).
 * Every call is logged in ai_usages with tokens and estimated cost.
 */
class Claude
{
    private const PAUSE_KEY = 'ai.paused';

    public function available(): bool
    {
        return (bool) config('ai.enabled')
            && filled(config('ai.api_key'))
            && !Cache::has(self::PAUSE_KEY)
            && !$this->overMonthlyLimit();
    }

    /** Why AI is not available right now (null = available), for Sozlamalar. */
    public function unavailableReason(): ?string
    {
        return match (true) {
            !config('ai.enabled')           => 'disabled',
            blank(config('ai.api_key'))     => 'no_key',
            Cache::has(self::PAUSE_KEY)     => (string) (Cache::get(self::PAUSE_KEY)['reason'] ?? 'paused'),
            $this->overMonthlyLimit()       => 'limit',
            default                         => null,
        };
    }

    public function pausedUntil(): ?string
    {
        return Cache::get(self::PAUSE_KEY)['until'] ?? null;
    }

    public function resume(): void
    {
        Cache::forget(self::PAUSE_KEY);
    }

    /**
     * Asks Claude for JSON matching $schema. Returns the decoded array, or null (use the fallback).
     *
     * @param string $effort low / medium / high — depth of thinking for this task
     */
    public function json(string $feature, string $system, string $prompt, array $schema, int $maxTokens = 4000, string $effort = 'low'): ?array
    {
        if (!$this->available()) {
            return null;
        }

        $model = $this->model($feature);
        $params = [
            'model'        => $model,
            'maxTokens'    => $maxTokens,
            'system'       => $system,
            'messages'     => [['role' => 'user', 'content' => $prompt]],
            'outputConfig' => ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];
        if (in_array(app(ModelResolver::class)->lineOf($model), config('ai.fallback_lines', []), true)) {
            // on a safety refusal the API retries on a suitable model by itself (not available on Haiku)
            $params += ['betas' => ['server-side-fallback-2026-07-01'], 'fallbacks' => 'default'];
        }

        try {
            $message = $this->send($params);
        } catch (\Throwable $e) {
            $this->failed($feature, $e);

            return null;
        }

        $usage = $message->usage ?? null;
        $in = (int) ($usage->inputTokens ?? 0) + (int) ($usage->cacheReadInputTokens ?? 0) + (int) ($usage->cacheCreationInputTokens ?? 0);
        $out = (int) ($usage->outputTokens ?? 0);

        if (($message->stopReason ?? null) === 'refusal') {
            $this->log($feature, $in, $out, 'refusal', (string) ($message->stopDetails->category ?? ''), $model);
            return null;
        }

        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        $data = json_decode($text, true);
        $this->log($feature, $in, $out, is_array($data) ? 'ok' : 'error', is_array($data) ? null : 'invalid JSON', $model);

        return is_array($data) ? $data : null;
    }

    /** Model for a task: the newest model of the task's level (see ModelResolver). */
    public function model(string $feature): string
    {
        return app(ModelResolver::class)->forTask($feature);
    }

    /** The real API call — separate so tests can replace it. */
    protected function send(array $params): object
    {
        $client = new Client(apiKey: config('ai.api_key'), requestOptions: ['timeout' => 120, 'maxRetries' => 2]);

        return $client->beta->messages->create(...$params);
    }

    private function failed(string $feature, \Throwable $e): void
    {
        $kind = self::classify($e);
        $this->log($feature, 0, 0, $kind, mb_substr($e->getMessage(), 0, 500), $this->model($feature));
        if ($e instanceof \Anthropic\Core\Exceptions\NotFoundException) {
            app(ModelResolver::class)->refresh(); // the cached model may have been retired — pick again next time
        }

        if (in_array($kind, ['credit', 'auth'], true)) {
            $until = now()->addMinutes(max(1, (int) config('ai.pause_minutes')));
            Cache::put(self::PAUSE_KEY, ['reason' => $kind, 'until' => $until->toIso8601String()], $until);
            $this->alertAdmin($kind);
        } else {
            Log::warning('Claude API call failed', ['feature' => $feature, 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    /** credit (money/spend limit) / auth (key) / retry (temporary) / error. */
    public static function classify(\Throwable $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            $e instanceof PaymentRequiredException,
            $e instanceof EnforcedSpendLimitReachedException,
            $e instanceof ContractSpendLimitReachedException,
            $e instanceof LinkedOrganizationSpendLimitReachedException => 'credit',
            $e instanceof BadRequestException && (str_contains($message, 'credit balance') || str_contains($message, 'billing')) => 'credit',
            $e instanceof AuthenticationException, $e instanceof PermissionDeniedException => 'auth',
            $e instanceof RateLimitException, $e instanceof APIConnectionException => 'retry',
            $e instanceof APIStatusException && ($e->status ?? 0) >= 500 => 'retry',
            default => 'error',
        };
    }

    private function alertAdmin(string $kind): void
    {
        try {
            $notifier = app(Notifier::class);
            if ($chat = $notifier->adminChat()) {
                app(TelegramApi::class)->sendMessage($chat, '⚠️ ' . __('ai.alert_' . $kind, ['minutes' => config('ai.pause_minutes'), 'url' => route('admin.settings')]));
            }
        } catch (\Throwable) {
            // an alert must never break the feature
        }
    }

    private function overMonthlyLimit(): bool
    {
        $limit = (float) config('ai.monthly_limit_usd');

        return $limit > 0 && AiUsage::monthCost() >= $limit;
    }

    private function log(string $feature, int $in, int $out, string $status, ?string $error, string $model): void
    {
        [$priceIn, $priceOut] = config('ai.prices.' . app(ModelResolver::class)->lineOf($model), [0, 0]);
        AiUsage::create([
            'feature' => $feature, 'model' => $model,
            'input_tokens' => $in, 'output_tokens' => $out,
            'cost_usd' => ($in * (float) $priceIn + $out * (float) $priceOut) / 1_000_000,
            'status' => $status, 'error' => $error,
        ]);
    }
}
