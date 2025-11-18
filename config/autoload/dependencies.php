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
    \HyperfExtension\Cookie\Contract\CookieJarInterface::class => \HyperfExtension\Cookie\CookieJar::class,
    
    /**
     * 自定义 StdoutLogger 绑定
     * 
     * 原因：
     * Hyperf 框架默认的 StdoutLogger 在处理日志 context 中的嵌套数组时存在问题。
     * 当调用 logger()->info() 等方法并传入包含嵌套数组的 context（如 ['menu_values' => ['key' => 'value']]）时，
     * 原实现会在 str_replace() 方法中报错，因为 str_replace() 无法处理数组类型的值。
     * 
     * 解决方案：
     * 使用自定义的 App\Logging\StdoutLogger，在 log() 方法中对 context 中的嵌套数组进行预处理，
     * 将数组转换为 JSON 字符串，确保所有 context 值都是字符串类型，从而避免 str_replace() 报错。
     * 
     * 主要改进：
     * - 支持嵌套数组：自动将 context 中的数组转换为 JSON 字符串
     * - 保持兼容性：完全实现 StdoutLoggerInterface 接口，不影响其他功能
     * - 错误处理：如果 JSON 编码失败，会显示友好的错误信息
     */
    \Hyperf\Contract\StdoutLoggerInterface::class => \App\Logging\StdoutLogger::class,

];
