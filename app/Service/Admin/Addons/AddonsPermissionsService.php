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

namespace App\Service\Admin\Addons;

use App\Model\Admin\AdminPermission;

/**
 * 插件权限管理服务
 *
 * 负责插件权限的安装、卸载和管理功能
 */
class AddonsPermissionsService
{
    /**
     * 安装插件权限.
     *
     * @param string $addonName 插件名称
     * @param array $permissions 权限配置
     */
    public function installAddonPermissions(string $addonName, array $permissions): void
    {
        logger()->info("[插件安装] 开始安装权限，插件: {$addonName}");

        $this->installPermissionTree($permissions, $addonName);

        logger()->info('[插件安装] 权限安装完成');
    }

    /**
     * 删除插件权限.
     *
     * @param string $addonName 插件名称
     * @param array $permissions 权限配置
     */
    public function deleteAddonPermissions(string $addonName, array $permissions): void
    {
        logger()->info("[插件禁用] 开始删除权限，插件: {$addonName}");

        $this->deletePermissionTree($permissions, $addonName);

        logger()->info('[插件禁用] 权限删除完成');
    }

    /**
     * 递归安装权限树.
     *
     * @param array $permissions 权限配置数组
     * @param string $addonName 插件名称
     * @param int $parentId 父级权限ID
     */
    private function installPermissionTree(array $permissions, string $addonName, int $parentId = 0): void
    {
        foreach ($permissions as $permission) {
            // 检查权限是否已存在
            if ($this->permissionExists($permission['slug'])) {
                logger()->info("[插件安装] 权限已存在，跳过: {$permission['name']} ({$permission['slug']})");
                // 即使权限存在，也要处理子权限
                if (isset($permission['children']) && is_array($permission['children'])) {
                    $existingPermissionId = $this->getPermissionIdBySlug($permission['slug']);
                    if ($existingPermissionId) {
                        $this->installPermissionTree($permission['children'], $addonName, $existingPermissionId);
                    }
                }
                continue;
            }

            // 准备权限数据
            $permissionData = $permission;
            $permissionData['parent_id'] = $parentId;

            // 创建权限
            $permissionId = $this->createPermission($permissionData, $addonName);
            logger()->info("[插件安装] 成功创建权限: {$permission['name']} ({$permission['slug']}) - ID: {$permissionId}");

            // 递归处理子权限
            if (isset($permission['children']) && is_array($permission['children'])) {
                $this->installPermissionTree($permission['children'], $addonName, $permissionId);
            }
        }
    }

    /**
     * 递归删除权限树.
     *
     * @param array $permissions 权限配置数组
     * @param string $addonName 插件名称
     */
    private function deletePermissionTree(array $permissions, string $addonName): void
    {
        foreach ($permissions as $permission) {
            // 递归删除子权限（先删除子权限）
            if (isset($permission['children']) && is_array($permission['children'])) {
                $this->deletePermissionTree($permission['children'], $addonName);
            }

            // 删除当前权限
            $this->deletePermission($permission['slug'], $addonName);
            logger()->info("[插件禁用] 删除权限: {$permission['name']} ({$permission['slug']})");
        }
    }

    /**
     * 检查权限是否存在.
     *
     * @param string $slug 权限标识
     */
    private function permissionExists(string $slug): bool
    {
        try {
            return AdminPermission::query()
                ->where('slug', $slug)
                ->exists();
        } catch (Throwable $e) {
            logger()->error("[插件检查] 权限存在性检查失败: {$slug} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据slug获取权限ID.
     *
     * @param string $slug 权限标识
     */
    private function getPermissionIdBySlug(string $slug): ?int
    {
        try {
            $permission = AdminPermission::query()
                ->where('slug', $slug)
                ->first();

            return $permission?->id;
        } catch (Throwable $e) {
            logger()->error("[插件查询] 权限ID查询失败: {$slug} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建权限.
     *
     * @param array $permissionData 权限数据
     * @param string $addonName 插件名称
     */
    private function createPermission(array $permissionData, string $addonName): int
    {
        try {
            // 准备权限数据 - 只设置JSON中提供的字段，让数据库使用默认值
            $permissionAttributes = [
                'site_id' => $permissionData['site_id'] ?? 0, // 默认全局站点
                'parent_id' => $permissionData['parent_id'] ?? 0,
                'name' => $permissionData['name'] ?? '',
                'slug' => $permissionData['slug'] ?? '',
                'type' => $permissionData['type'] ?? 'menu',
                'path' => $permissionData['path'] ?? '',
                'description' => $permissionData['description'] ?? null,
                'status' => $permissionData['status'] ?? 1,
                'sort' => $permissionData['sort'] ?? 0,
            ];

            // 可选字段 - 只有在JSON中明确指定时才设置
            if (isset($permissionData['icon'])) {
                $permissionAttributes['icon'] = $permissionData['icon'];
            }
            if (isset($permissionData['method'])) {
                $permissionAttributes['method'] = $permissionData['method'];
            }
            if (isset($permissionData['component'])) {
                $permissionAttributes['component'] = $permissionData['component'];
            }

            // 检查权限是否已存在
            $existingPermission = AdminPermission::query()
                ->where('slug', $permissionAttributes['slug'])
                ->first();

            if ($existingPermission) {
                logger()->info("[插件安装] 权限已存在，跳过创建: {$permissionAttributes['name']} (ID: {$existingPermission->id})");
                return $existingPermission->id;
            }

            // 创建权限
            $permission = AdminPermission::query()->create($permissionAttributes);

            logger()->info("[插件安装] 权限创建成功: {$permissionAttributes['name']} (ID: {$permission->id})");
            return $permission->id;
        } catch (Throwable $e) {
            logger()->error("[插件安装] 权限创建失败: {$permissionData['name']} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除权限.
     *
     * @param string $slug 权限标识
     * @param string $addonName 插件名称
     */
    private function deletePermission(string $slug, string $addonName): void
    {
        try {
            $deleted = AdminPermission::query()
                ->where('slug', $slug)
                ->delete();

            if ($deleted > 0) {
                logger()->info("[插件操作] 权限删除成功: {$slug}");
            } else {
                logger()->warning("[插件操作] 权限删除失败，未找到权限: {$slug}");
            }
        } catch (Throwable $e) {
            logger()->error("[插件操作] 权限删除异常: {$slug} - " . $e->getMessage());
        }
    }

    /**
     * 更新权限状态
     *
     * @param string $slug 权限标识
     * @param int $status 状态
     * @param string $addonName 插件名称
     */
    public function updatePermissionStatus(string $slug, int $status, string $addonName): void
    {
        try {
            $updated = AdminPermission::query()
                ->where('slug', $slug)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                $statusText = $status === 1 ? '启用' : '禁用';
                logger()->info("[插件操作] 权限状态更新成功: {$slug} -> {$statusText}");
            } else {
                logger()->warning("[插件操作] 权限状态更新失败，未找到权限: {$slug}");
            }
        } catch (Throwable $e) {
            logger()->error("[插件操作] 权限状态更新异常: {$slug} - " . $e->getMessage());
        }
    }

    /**
     * 获取权限列表
     *
     * @param string $addonName 插件名称
     * @return array 权限列表
     */
    public function getAddonPermissions(string $addonName): array
    {
        try {
            $permissions = AdminPermission::query()
                ->where('description', 'like', "%{$addonName}%")
                ->orWhere('slug', 'like', "{$addonName}%")
                ->get()
                ->toArray();

            return $permissions;
        } catch (Throwable $e) {
            logger()->error("[插件查询] 获取插件权限列表失败: {$addonName} - " . $e->getMessage());
            return [];
        }
    }

    /**
     * 清理插件权限
     *
     * @param string $addonName 插件名称
     * @return int 删除的权限数量
     */
    public function cleanupAddonPermissions(string $addonName): int
    {
        try {
            $deleted = AdminPermission::query()
                ->where('description', 'like', "%{$addonName}%")
                ->orWhere('slug', 'like', "{$addonName}%")
                ->delete();

            logger()->info("[插件清理] 清理插件权限完成: {$addonName}, 删除数量: {$deleted}");
            return $deleted;
        } catch (Throwable $e) {
            logger()->error("[插件清理] 清理插件权限失败: {$addonName} - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * 检查用户是否有权限
     *
     * @param int $userId 用户ID
     * @param string $permissionSlug 权限标识
     * @return bool 是否有权限
     */
    public function hasPermission(int $userId, string $permissionSlug): bool
    {
        try {
            // 这里可以实现复杂的权限检查逻辑
            // 比如通过用户角色关联表检查用户是否有该权限
            // 目前返回true作为示例
            return true;
        } catch (Throwable $e) {
            logger()->error("[权限检查] 检查用户权限失败: user_id={$userId}, permission={$permissionSlug} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * 获取用户的权限列表
     *
     * @param int $userId 用户ID
     * @return array 权限列表
     */
    public function getUserPermissions(int $userId): array
    {
        try {
            // 这里可以实现获取用户权限列表的逻辑
            // 目前返回空数组作为示例
            return [];
        } catch (Throwable $e) {
            logger()->error("[权限查询] 获取用户权限列表失败: user_id={$userId} - " . $e->getMessage());
            return [];
        }
    }
}
