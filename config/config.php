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
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;

use function Hyperf\Support\env;

return [
    'app_name' => env('APP_NAME', 'skeleton'),
    'app_env' => env('APP_ENV', 'dev'),
    'scan_cacheable' => env('SCAN_CACHEABLE', false),

    // 日志配置：需要打印 context 内容的标签前缀
    // 匹配的日志消息前缀，满足条件时会打印 context 数组内容
    'log_context_tags' => [
        // '[插件商店]',
        // '[插件导入]',
        '[插件导出]',
        // '[数据库管理]',
        // 可以添加更多需要打印 context 的标签
        // '[用户管理]',
        // '[系统配置]',
    ],

    StdoutLoggerInterface::class => [
        'log_level' => [
//            LogLevel::ALERT,
//            LogLevel::CRITICAL,
//           LogLevel::DEBUG,
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING,
        ],
    ],
];
