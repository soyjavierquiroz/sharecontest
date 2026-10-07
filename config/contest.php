<?php

return [
    'hashtag' => env('CONTEST_HASHTAG', '#MiConcurso2026'),
    'host' => env('APP_HOST', 'sharecontest.kuruk.in'),
    'admin_user' => env('ADMIN_USER'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'api_token' => env('SHARECONTEST_API_TOKEN'),
    'api_rate_limit' => max(1, (int) env('SHARECONTEST_API_RATE_LIMIT', 60)),
];
