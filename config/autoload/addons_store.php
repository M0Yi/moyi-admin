<?php

declare(strict_types=1);

/**
 * 插件商店配置
 */

use function Hyperf\Support\env;

return [
    // 插件商店API URL
    'api_url' => env('ADDONS_STORE_API_URL', 'http://127.0.0.1:6501/api/addons_store'),

    // API Token认证
    'api_token' => env('ADDONS_STORE_API_TOKEN', ''),
];