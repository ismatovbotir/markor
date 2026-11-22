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
];
