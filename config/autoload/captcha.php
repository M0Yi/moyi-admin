<?php

declare(strict_types=1);

return [
    'challenge_ttl' => 120,
    'pass_ttl' => 300,
    'allowed_offset' => 6,
    'redis_pool' => 'default',
    'headers' => [
        'challenge' => 'X-Captcha-Token',
        'pass' => 'X-Captcha-Pass',
    ],
];

