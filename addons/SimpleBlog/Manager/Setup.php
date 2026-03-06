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

namespace Addons\SimpleBlog\Manager;

use Hyperf\Support\Traits\StaticInstance;

/**
 * SimpleBlog 插件生命周期管理.
 *
 * 向量处理由 VectorService 单独处理
 *
 * 插件启用时的环境检查由系统统一处理（AddonService.checkAddonEnableRequirements）
 * - PostgreSQL 扩展检查：基于 pgsql.json 中的 extensions 配置
 * - 自定义检查：通过 config.php 中的 checkers 配置
 */
class Setup
{
    use StaticInstance;

    /**
     * 插件安装时执行.
     */
    public function install(): bool
    {
        logger()->info('[SimpleBlog] 插件安装完成');

        return true;
    }

    /**
     * 插件启用时执行.
     *
     * 注意：环境检查已由系统 AddonService 统一处理
     * 如果需要自定义检查逻辑，请在 config.php 中配置 checkers
     */
    public function enable(): bool
    {
        logger()->info('[SimpleBlog] 插件启用完成');

        return true;
    }

    /**
     * 插件禁用时执行.
     */
    public function disable(): bool
    {
        logger()->info('[SimpleBlog] 插件禁用完成');

        return true;
    }

    /**
     * 插件卸载时执行.
     */
    public function uninstall(): bool
    {
        logger()->info('[SimpleBlog] 插件卸载完成');

        return true;
    }

    /**
     * 获取环境检查状态（用于后台显示）.
     *
     * @deprecated 由系统 AddonService.getAddonCheckStatus() 替代
     */
    public function getEnvironmentStatus(): array
    {
        // 该方法已废弃，由系统统一处理
        return [
            'deprecated' => true,
            'message' => '请使用 AddonService.getAddonCheckStatus() 获取检查状态',
        ];
    }
}
