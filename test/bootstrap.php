<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap
 * 
 * 支持两种运行模式：
 * 1. 有 Swoole：完整加载 Hyperf 容器
 * 2. 无 Swoole：只加载 Composer 自动加载
 */

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

// 检查 Swoole 是否可用
$hasSwoole = extension_loaded('swoole');

if (!$hasSwoole) {
    // 无 Swoole 环境，只加载自动加载，不初始化容器
    echo "⚠️  Swoole 未安装，部分测试将被跳过\n";
    echo "💡 安装 Swoole: brew install PECL/swoole/swoole 或 pecl install swoole\n\n";
    return;
}

// 有 Swoole，完整初始化 Hyperf
! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', Hyperf\Engine\DefaultOption::hookFlags());

Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_FLAGS);

Hyperf\Di\ClassLoader::init();

$container = require BASE_PATH . '/config/container.php';

$container->get(Hyperf\Contract\ApplicationInterface::class);
