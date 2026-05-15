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
    'id' => 'simple_blog',

    /*
     * 插件名称
     */
    'name' => '简单博客',

    /*
     * 插件版本
     */
    'version' => '1.0.0',

    /*
     * 插件描述
     */
    'description' => '一个极其简单的博客插件，提供基本的文章发布和管理功能',

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
    'category' => 'content',
];
