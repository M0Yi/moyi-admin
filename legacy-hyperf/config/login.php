<?php

declare(strict_types=1);

/**
 * 登录配置
 *
 * 控制登录相关的功能开关
 */

use function Hyperf\Support\env;

return [
    /**
     * 是否启用登录验证码
     * true: 启用验证码（默认）
     * false: 禁用验证码
     */
    'captcha_enabled' => env('LOGIN_CAPTCHA_ENABLED', true),
];
