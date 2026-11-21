<?php

// Charging per DataMatrix code loaded into the database (a batch's processed_rows).
// Price and discount are copied onto each batch when it finishes loading, so changing them
// later does not change what earlier batches cost.
return [
    'currency'         => env('BILLING_CURRENCY', "so'm"),
    // Card clients transfer prepayments to (edited in Sozlamalar)
    'card_number'      => env('BILLING_CARD_NUMBER'),
    'card_holder'      => env('BILLING_CARD_HOLDER'),
    'price_per_code'   => (float) env('BILLING_PRICE_PER_CODE', 9),
    // Default discount after a company's own discount (e.g. the welcome month) has ended. 0 = full price.
    'discount_percent' => (float) env('BILLING_DISCOUNT_PERCENT', 0),
    // Welcome discount for a newly registered company: percent for N months (0 months = none).
    // free period of a new company in months (companies.free_until); the super admin can prolong it per company
    'free_months'      => (int) env('BILLING_FREE_MONTHS', 1),
    // free period cap: at most this many codes are free; the rest of the period is charged normally (0 = no cap)
    'free_codes_limit' => (int) env('BILLING_FREE_CODES_LIMIT', 5000),
];
