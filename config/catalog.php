<?php

return [
    // Product info by GTIN (national classifier tasnif.soliq.uz): name, MXIK code, units.
    // {gtin} is replaced by the 14-digit GTIN (or 13-digit barcode). Open GET, no key.
    'gtin_lookup_url' => env('GTIN_LOOKUP_URL', 'https://tasnif.soliq.uz/api/cl-api/integration-mxik/get/information/?shtrixCode={gtin}'),
    // The same classifier's search, which answers in a chosen language: used for the official Russian name.
    // {gtin} = barcode, {lang} = ru. Open GET, no key.
    'gtin_search_url' => env('GTIN_SEARCH_URL', 'https://tasnif.soliq.uz/api/cls-api/elasticsearch/search?search={gtin}&size=5&page=0&lang={lang}'),
];
