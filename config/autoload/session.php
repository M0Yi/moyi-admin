<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\Session\Handler;

return [
    // 使用多站点 Redis Handler，确保不同站点的 session 数据隔离
    'handler' => App\Session\Handler\MultiSiteRedisHandler::class,
    'options' => [
        'connection' => 'default',
        'path' => BASE_PATH . '/runtime/session',
        'gc_maxlifetime' => 1200,
        'session_name' => 'HYPERF_SESSION_ID',
        // domain 设置为 null，由 MultiSiteSessionMiddleware 根据当前站点动态设置
        'domain' => null,
        'cookie_lifetime' => 5 * 60 * 60,
        'cookie_same_site' => 'lax',
    ],
];
