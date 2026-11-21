<?php

return [
    // Product info by GTIN (national classifier tasnif.soliq.uz): name, MXIK code, units.
    // {gtin} is replaced by the 14-digit GTIN (or 13-digit barcode). Open GET, no key.
    'gtin_lookup_url' => env('GTIN_LOOKUP_URL', 'https://tasnif.soliq.uz/api/cl-api/integration-mxik/get/information/?shtrixCode={gtin}'),
];
