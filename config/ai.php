<?php

/*
 * Claude API (Anthropic). Used for: product names from the tax classifier, Marketplace item texts
 * and FAQ. Every feature has a fallback, so the site keeps working without a key, when the credit
 * runs out or when the API is down (see App\Domain\Ai\Claude).
 *
 * Models are NOT hard-coded: each task picks a LEVEL (haiku / sonnet / opus) and the app asks the
 * Models API for the newest active model of that line (cached for a day). When Claude Haiku 6
 * comes out, tasks on "haiku" switch to it by themselves.
 */
return [
    'enabled' => (bool) env('AI_ENABLED', true),
    'api_key' => env('ANTHROPIC_API_KEY'),

    // level per task (changeable in Sozlamalar): haiku = cheapest/fastest, sonnet = better texts, opus = strongest
    'levels' => [
        'product_name'  => env('AI_LEVEL_PRODUCT_NAME', 'haiku'),
        'offer_content' => env('AI_LEVEL_OFFER_CONTENT', 'haiku'),
    ],
    'level_options' => ['haiku', 'sonnet', 'opus'],

    // optional exact model per task, only to pin one on purpose (empty = newest of the level)
    'pinned' => [
        'product_name'  => env('AI_MODEL_PRODUCT_NAME'),
        'offer_content' => env('AI_MODEL_OFFER_CONTENT'),
    ],

    // used only if the Models API can't be reached: last known model of each line
    'known_models' => ['haiku' => 'claude-haiku-5-5', 'sonnet' => 'claude-sonnet-5-5', 'opus' => 'claude-opus-5-5'],
    'models_cache_hours' => 24,

    // USD per 1M tokens [input, output] by line, for the cost counter (estimate for newer versions of a line)
    'prices' => ['haiku' => [0.10, 0.50], 'sonnet' => [2, 10], 'opus' => [4, 20]],

    // lines that support server-side fallback on a safety refusal (Haiku does not)
    'fallback_lines' => ['opus', 'sonnet', 'fable'],

    'pause_minutes' => (int) env('AI_PAUSE_MINUTES', 60),
    'monthly_limit_usd' => (float) env('AI_MONTHLY_LIMIT_USD', 0),
];
