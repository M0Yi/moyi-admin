<?php

declare(strict_types=1);

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
    'public_creation_enabled' => (bool) \Hyperf\Support\env('ENABLE_PUBLIC_SITE_CREATION', false),
    'default_role_id' => (int) \Hyperf\Support\env('SITE_PUBLIC_DEFAULT_ROLE_ID', 2),
    'verification_token_ttl' => (int) \Hyperf\Support\env('SITE_VERIFICATION_TOKEN_TTL', 300),
    'verify_dns' => (bool) \Hyperf\Support\env('SITE_VERIFY_DNS', true),
    'validate_hostname' => (bool) \Hyperf\Support\env('SITE_VALIDATE_HOSTNAME', true),
];


