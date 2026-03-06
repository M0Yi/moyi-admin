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
    // ========================================
    // 基本信息
    // ========================================

    /*
     * 插件标识
     * 必须唯一，用于系统识别插件，作为应用市场的标识符ID
     */
    'id' => 'pgsql_tester',

    /*
     * 插件名称
     */
    'name' => 'PostgreSQL 测试',

    /*
     * 插件版本
     */
    'version' => '1.0.0',

    /*
     * 插件描述
     */
    'description' => 'PostgreSQL 数据库连接和查询测试插件，提供连接测试、查询测试、性能监控等功能',

    // ========================================
    // 作者信息
    // ========================================

    /*
     * 作者姓名
     */
    'author' => 'Moyi Admin Team',

    /*
     * 作者邮箱
     */
    'email' => 'moyi@mymoyi.cn',

    // ========================================
    // 技术信息
    // ========================================

    /*
     * 支持的 Moyi Admin 版本
     */
    'moyi_admin_version' => '>=1.0.0',

    /*
     * 插件类型
     */
    'type' => 'admin',

    /*
     * 插件分类
     */
    'category' => 'database',
];

