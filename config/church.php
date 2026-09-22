<?php

return [
    'auth' => [
        'max_attempts' => (int) env('CHURCH_AUTH_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('CHURCH_AUTH_DECAY_SECONDS', 60),
    ],
];
