<?php

namespace App\Domain\Ai;

use Anthropic\Client;
use App\Domain\Settings\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Which Claude model a task uses — never hard-coded:
 *   task → level (Sozlamalar, else config ai.levels) → newest ACTIVE model of that line from the
 *   Models API (`line` field), cached for a day. A pinned model (AI_MODEL_<TASK>) wins if set.
 * If the Models API can't be reached, the last known model of the line is used.
 */
class ModelResolver
{
    public function level(string $task): string
    {
        $level = Setting::all_()["ai.level.$task"] ?? null ?: config("ai.levels.$task") ?: 'haiku';

        return in_array($level, config('ai.level_options'), true) ? $level : 'haiku';
    }

    public function forTask(string $task): string
    {
        return config("ai.pinned.$task") ?: $this->newest($this->level($task));
    }

    /** Newest active model of a line (haiku / sonnet / opus). */
    public function newest(string $line): string
    {
        $known = (string) config("ai.known_models.$line", 'claude-haiku-5-5');
        if (blank(config('ai.api_key'))) {
            return $known;
        }

        return Cache::remember("ai.model.$line", now()->addHours((int) config('ai.models_cache_hours', 24)), function () use ($line, $known) {
            try {
                $best = null;
                foreach ($this->activeModels() as $m) {
                    if (($m['line'] ?? null) === $line && (!$best || $m['created'] > $best['created'])) {
                        $best = $m;
                    }
                }

                return $best['id'] ?? $known;
            } catch (\Throwable $e) {
                Log::warning('Claude model list failed, using the known model', ['line' => $line, 'error' => $e->getMessage()]);

                return $known;
            }
        });
    }

    /** Forget cached choices (after a new model is announced, or a model was retired). */
    public function refresh(): void
    {
        foreach (config('ai.level_options') as $line) {
            Cache::forget("ai.model.$line");
        }
    }

    /** Line of a model id as the API reports it, for prices and fallback support. */
    public function lineOf(string $model): string
    {
        foreach (config('ai.level_options') as $line) {
            if (Cache::get("ai.model.$line") === $model || config("ai.known_models.$line") === $model) {
                return $line;
            }
        }

        return str_contains($model, 'opus') ? 'opus' : (str_contains($model, 'sonnet') ? 'sonnet' : 'haiku');
    }

    /** @return iterable<array{id:string, line:?string, created:int}> — separate so tests can replace it */
    protected function activeModels(): iterable
    {
        $client = new Client(apiKey: config('ai.api_key'), requestOptions: ['timeout' => 20, 'maxRetries' => 1]);
        foreach ($client->models->list(lifecycle: ['active'], limit: 100)->pagingEachItem() as $model) {
            yield ['id' => $model->id, 'line' => $model->line, 'created' => $model->createdAt->getTimestamp()];
        }
    }
}
