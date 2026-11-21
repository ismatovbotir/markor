<?php

// Company details by tax number (STIR / PINFL). Leave the URL empty to let clients type them in.
return [
    'lookup_url'           => env('COMPANY_LOOKUP_URL'),           // with {tin}
    'lookup_authorization' => env('COMPANY_LOOKUP_AUTHORIZATION'), // full Authorization header value, e.g. "Basic ..."
];
