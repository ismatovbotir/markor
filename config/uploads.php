<?php

/*
 * Upload limits, checked twice: in the browser before sending (so a wrong or huge file never
 * travels to the server) and on the server (size + real content type, not only the file name).
 * Keep PHP's upload_max_filesize / post_max_size at least this big (PHP default: 2M / 8M).
 */
return [
    // Asl Belgisi code files (site + Telegram bot): 10 000 long codes ≈ 0.9 MB
    'codes' => ['max_kb' => (int) env('UPLOAD_CODES_MAX_KB', 2048), 'extensions' => ['csv', 'txt']],

    // public label designer: any spreadsheet, at most 300 rows are used
    'studio' => ['max_kb' => (int) env('UPLOAD_STUDIO_MAX_KB', 2048), 'extensions' => ['xlsx', 'ods', 'csv', 'txt']],

    // Marketplace product photos
    'image' => ['max_kb' => (int) env('UPLOAD_IMAGE_MAX_KB', 2048), 'extensions' => ['jpg', 'jpeg', 'png', 'webp']],
];
