<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    /*
    |--------------------------------------------------------------------------
    | Public Site Creation
    |--------------------------------------------------------------------------
    |
    | When set to true, visitors can open the self-service site creation wizard.
    | This should normally stay disabled to avoid unauthorized registrations.
    |
    */
    'public_creation_enabled' => (bool) env('ENABLE_PUBLIC_SITE_CREATION', false),
    'default_role_id' => (int) env('SITE_PUBLIC_DEFAULT_ROLE_ID', 2),
    'verification_token_ttl' => (int) env('SITE_VERIFICATION_TOKEN_TTL', 300),
    'verify_dns' => (bool) env('SITE_VERIFY_DNS', true),
    'validate_hostname' => (bool) env('SITE_VALIDATE_HOSTNAME', true),

    /*
    |--------------------------------------------------------------------------
    | AI Configuration (Default)
    |--------------------------------------------------------------------------
    |
    | Default AI configuration used when site-specific AI config is not set.
    | These values can be overridden by site-specific configurations.
    |
    */
    'ai' => [
        'token' => env('AI_TOKEN', ''),
        'base_url' => env('AI_BASE_URL', 'https://open.bigmodel.cn/api/paas/v4'),
        'text_model' => env('AI_TEXT_MODEL', 'glm-z1-flash'),
        'image_model' => env('AI_IMAGE_MODEL', 'cogview-3-flash'),
        'video_model' => env('AI_VIDEO_MODEL', 'cogvideox-flash'),
        'provider' => env('AI_PROVIDER', 'zhipu'),
    ],
];


