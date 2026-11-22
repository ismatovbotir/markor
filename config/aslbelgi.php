<?php

/*
 * Asl Belgisi (CRPT Turon xTrace) OPEN API — read-only use.
 * Each company's owner enters their own business-user API key in the company profile.
 * Reference: .claude/skills/aslbelgi-api (OPEN API v1.45.0).
 */
return [
    // prod: https://xtrace.aslbelgisi.uz · test: https://xtrace.stage.aslbelgisi.uz
    'base_url' => env('ASLBELGI_BASE_URL', 'https://xtrace.aslbelgisi.uz'),
    'timeout'  => (int) env('ASLBELGI_TIMEOUT', 15),

    // "Asl Belgisi tekshiruvi" of a batch (POST /public/api/cod/private/codes)
    'check' => [
        'chunk'          => 1000,               // API limit: ≤ 1000 codes per request
        'per_second'     => 10,                 // API limit 100 rps per user, planned 10: we stay at 10
        'retry_delays_ms'=> [1000, 3000, 7000], // 429 / 5xx / error code 1 / connection: at most 3 tries
    ],

    /*
     * Registry status of a code (API §13.15) → our problem reason. A code with a problem is NOT printed
     * (print page, PDF, Telegram PDF); the batch shows a warning with counts per reason.
     *
     * !!! The OPEN API PDF gives NO descriptions for these statuses. This mapping is our reading and
     * !!! MUST be confirmed with a real key (stage server) before relying on it. Statuses not listed
     * !!! here (e.g. RECEIVED, or new ones) are treated as fine; the raw status is stored on every code.
     *
     * Reasons that do not come from a status: not_ours (forbiddenCodes, HTTP 307, or the current owner's
     * TIN is not the company's), unknown (code not found in the registry), invalid (code rejected before
     * sending: shorter than 20 characters or with characters the API refuses).
     */
    'problem_statuses' => [
        'APPLIED'     => 'applied',     // already applied (utilisation reported)
        'INTRODUCED'  => 'introduced',  // already in circulation
        'WITHDRAWN'   => 'withdrawn',   // withdrawn from circulation
        'WRITTEN_OFF' => 'cancelled',   // written off / cancelled
    ],
];
