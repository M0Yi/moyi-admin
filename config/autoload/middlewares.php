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
return [
    'http' => [
        // 站点识别中间件（放在最前面，优先识别站点）
        \App\Middleware\SiteMiddleware::class,

        // Session 中间件
        \Hyperf\Session\Middleware\SessionMiddleware::class,

        // 验证中间件
        \Hyperf\Validation\Middleware\ValidationMiddleware::class,

    ],
];
