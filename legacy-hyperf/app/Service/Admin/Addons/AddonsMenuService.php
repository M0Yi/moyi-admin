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

use App\Model\Admin\AdminMenu;

/**
 * 插件菜单管理服务
 *
 * 负责插件菜单的安装、卸载和管理功能
 */
class AddonsMenuService
{
    /**
     * 安装插件菜单.
     *
     * @param string $addonName 插件名称
     * @param array $menus 菜单配置
     */
    public function installAddonMenus(string $addonName, array $menus): void
    {
        logger()->info("[插件安装] 开始安装菜单，插件: {$addonName}");

        $this->installMenuTree($menus, $addonName);

        logger()->info('[插件安装] 菜单安装完成');
    }

    /**
     * 删除插件菜单.
     *
     * @param string $addonName 插件名称
     * @param array $menus 菜单配置
     */
    public function deleteAddonMenus(string $addonName, array $menus): void
    {
        logger()->info("[插件禁用] 开始删除菜单，插件: {$addonName}");

        $this->deleteMenuTree($menus, $addonName);

        logger()->info('[插件禁用] 菜单删除完成');
    }

    /**
     * 递归安装菜单树.
     *
     * @param array $menus 菜单配置数组
     * @param string $addonName 插件名称
     * @param int $parentId 父级菜单ID
     */
    private function installMenuTree(array $menus, string $addonName, int $parentId = 0): void
    {
        foreach ($menus as $menu) {
            // 检查菜单是否已存在
            if ($this->menuExists($menu['name'])) {
                logger()->info("[插件安装] 菜单已存在，检查parent_id: {$menu['title']} ({$menu['name']})");

                // 获取现有菜单
                $existingMenuId = $this->getMenuIdByName($menu['name']);
                $existingMenu = AdminMenu::find($existingMenuId);

                // 检查parent_id是否正确，如果不正确则更新
                if ($existingMenu && $existingMenu->parent_id !== $parentId) {
                    $existingMenu->update(['parent_id' => $parentId]);
                    logger()->info("[插件安装] 更新菜单parent_id: {$menu['title']} (ID: {$existingMenuId}) parent_id: {$existingMenu->parent_id} -> {$parentId}");
                }

                // 如果菜单已存在，递归处理子菜单（获取现有菜单ID作为父ID）
                if ($existingMenuId && isset($menu['children']) && is_array($menu['children'])) {
                    logger()->info("[插件安装] 处理已存在菜单的子菜单: {$menu['title']}");
                    $this->installMenuTree($menu['children'], $addonName, $existingMenuId);
                }
                continue;
            }

            // 处理菜单数据
            $menuData = $menu;
            $menuData['parent_id'] = $parentId;

            // 移除children字段，避免保存到数据库
            $children = $menuData['children'] ?? [];
            unset($menuData['children']);

            // 设置默认值
            $menuData['site_id'] = $menuData['site_id'] ?? 0; // 默认全局站点

            // 创建菜单
            $menuId = $this->createMenu($menuData, $addonName);
            logger()->info("[插件安装] 成功创建菜单: {$menu['title']} ({$menu['name']}) - ID: {$menuId}, 路径: {$menu['path']}");

            // 递归处理子菜单
            if (! empty($children)) {
                logger()->info("[插件安装] 处理子菜单: {$menu['title']} -> " . count($children) . ' 个子菜单');
                $this->installMenuTree($children, $addonName, $menuId);
            }
        }
    }

    /**
     * 递归删除菜单树.
     *
     * @param array $menus 菜单配置数组
     * @param string $addonName 插件名称
     */
    private function deleteMenuTree(array $menus, string $addonName): void
    {
        foreach ($menus as $menu) {
            // 删除当前菜单
            $this->deleteMenu($menu['name'], $addonName);
            logger()->info("[插件禁用] 删除菜单: {$menu['title']} ({$menu['name']})");
        }
    }

    /**
     * 检查菜单是否存在.
     *
     * @param string $name 菜单名称
     */
    private function menuExists(string $name): bool
    {
        try {
            return AdminMenu::query()
                ->where('name', $name)
                ->exists();
        } catch (Throwable $e) {
            logger()->error("[插件检查] 菜单存在性检查失败: {$name} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * 根据name获取菜单ID.
     *
     * @param string $name 菜单名称
     */
    private function getMenuIdByName(string $name): ?int
    {
        try {
            $menu = AdminMenu::query()
                ->where('name', $name)
                ->first();

            return $menu ? $menu->id : null;
        } catch (Throwable $e) {
            logger()->error("[插件查询] 菜单ID查询失败: {$name} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * 创建菜单.
     *
     * @param array $menuData 菜单数据
     * @param string $addonName 插件名称
     */
    private function createMenu(array $menuData, string $addonName): int
    {
        try {
            // 准备菜单数据
            $menuAttributes = [
                'site_id' => $menuData['site_id'] ?? 0, // 默认全局站点
                'parent_id' => $menuData['parent_id'] ?? 0,
                'name' => $menuData['name'] ?? '',
                'title' => $menuData['title'] ?? $menuData['name'] ?? '',
                'icon' => $menuData['icon'] ?? null,
                'path' => $menuData['path'] ?? '',
                'component' => $menuData['component'] ?? null,
                'redirect' => $menuData['redirect'] ?? null,
                'type' => $menuData['type'] ?? AdminMenu::TYPE_MENU,
                'target' => $menuData['target'] ?? AdminMenu::TARGET_SELF,
                'badge' => $menuData['badge'] ?? null,
                'badge_type' => $menuData['badge_type'] ?? null,
                'permission' => $menuData['permission'] ?? null,
                'visible' => $menuData['visible'] ?? 1,
                'status' => $menuData['status'] ?? 1,
                'sort' => $menuData['sort'] ?? 0,
                'cache' => $menuData['cache'] ?? 1,
                'config' => $menuData['config'] ?? null,
                'remark' => $menuData['remark'] ?? null,
            ];

            // 如果设置了site_id为0，确保不重复创建全局菜单
            if ($menuAttributes['site_id'] === 0) {
                $existingMenu = AdminMenu::query()
                    ->where('site_id', 0)
                    ->where('path', $menuAttributes['path'])
                    ->first();

                if ($existingMenu) {
                    logger()->info("[插件安装] 全局菜单已存在，跳过创建: {$menuAttributes['title']} (ID: {$existingMenu->id})");
                    return $existingMenu->id;
                }
            }

            // 创建菜单
            $menu = AdminMenu::query()->create($menuAttributes);

            logger()->info("[插件安装] 菜单创建成功: {$menuAttributes['title']} (ID: {$menu->id})");
            return $menu->id;
        } catch (Throwable $e) {
            logger()->error("[插件安装] 菜单创建失败: {$menuData['title']} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 删除菜单.
     *
     * @param string $name 菜单名称
     * @param string $addonName 插件名称
     */
    private function deleteMenu(string $name, string $addonName): void
    {
        try {
            $deleted = AdminMenu::query()
                ->where('name', $name)
                ->delete();

            if ($deleted > 0) {
                logger()->info("[插件操作] 菜单删除成功: {$name}");
            } else {
                logger()->warning("[插件操作] 菜单删除失败，未找到菜单: {$name}");
            }
        } catch (Throwable $e) {
            logger()->error("[插件操作] 菜单删除异常: {$name} - " . $e->getMessage());
        }
    }

    /**
     * 更新菜单状态
     *
     * @param string $name 菜单名称
     * @param int $status 状态
     * @param string $addonName 插件名称
     */
    public function updateMenuStatus(string $name, int $status, string $addonName): void
    {
        try {
            $updated = AdminMenu::query()
                ->where('name', $name)
                ->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                $statusText = $status === 1 ? '启用' : '禁用';
                logger()->info("[插件操作] 菜单状态更新成功: {$name} -> {$statusText}");
            } else {
                logger()->warning("[插件操作] 菜单状态更新失败，未找到菜单: {$name}");
            }
        } catch (Throwable $e) {
            logger()->error("[插件操作] 菜单状态更新异常: {$name} - " . $e->getMessage());
        }
    }

    /**
     * 获取菜单列表
     *
     * @param string $addonName 插件名称
     * @return array 菜单列表
     */
    public function getAddonMenus(string $addonName): array
    {
        try {
            $menus = AdminMenu::query()
                ->where('remark', 'like', "%{$addonName}%")
                ->orWhere('name', 'like', "{$addonName}%")
                ->get()
                ->toArray();

            return $menus;
        } catch (Throwable $e) {
            logger()->error("[插件查询] 获取插件菜单列表失败: {$addonName} - " . $e->getMessage());
            return [];
        }
    }

    /**
     * 清理插件菜单
     *
     * @param string $addonName 插件名称
     * @return int 删除的菜单数量
     */
    public function cleanupAddonMenus(string $addonName): int
    {
        try {
            $deleted = AdminMenu::query()
                ->where('remark', 'like', "%{$addonName}%")
                ->orWhere('name', 'like', "{$addonName}%")
                ->delete();

            logger()->info("[插件清理] 清理插件菜单完成: {$addonName}, 删除数量: {$deleted}");
            return $deleted;
        } catch (Throwable $e) {
            logger()->error("[插件清理] 清理插件菜单失败: {$addonName} - " . $e->getMessage());
            return 0;
        }
    }
}
