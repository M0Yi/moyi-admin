<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminMenu;
use App\Service\Admin\MenuService;
use Hyperf\Di\Annotation\Inject;

/**
 * 管理后台仪表盘控制器
 */
class DashboardController extends AbstractController
{
    #[Inject]
    protected MenuService $menuService;

    /**
     * 仪表盘首页
     */
    public function index()
    {
        // 获取统计数据
        $stats = [
            'users' => [
                'total' => 150,
                'today' => 5,
                'icon' => 'users',
                'color' => 'primary',
            ],
            'sites' => [
                'total' => 3,
                'active' => 3,
                'icon' => 'globe',
                'color' => 'success',
            ],
            'activities' => [
                'total' => 45,
                'this_month' => 12,
                'icon' => 'calendar',
                'color' => 'info',
            ],
            'revenue' => [
                'total' => '¥128,500',
                'this_month' => '¥15,200',
                'icon' => 'dollar-sign',
                'color' => 'warning',
            ],
        ];

        // 最近活动
        $recentActivities = [
            [
                'user' => '张三',
                'action' => '创建了新活动',
                'target' => '2024年度技术峰会',
                'time' => '5分钟前',
            ],
            [
                'user' => '李四',
                'action' => '更新了用户资料',
                'target' => null,
                'time' => '15分钟前',
            ],
            [
                'user' => '王五',
                'action' => '删除了活动',
                'target' => '测试活动',
                'time' => '1小时前',
            ],
        ];

        // 获取快捷入口
        $quickShortcuts = $this->getQuickShortcuts();

        return $this->renderAdmin('admin.dashboard.index', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
            'quickShortcuts' => $quickShortcuts,
        ]);
    }

    /**
     * 获取快捷入口菜单
     * 自动从菜单系统中提取常用功能作为快捷入口
     */
    protected function getQuickShortcuts(): array
    {
        // 定义快捷入口的菜单名称匹配规则
        $shortcutRules = [
            'users' => [
                'title' => '用户管理',
                'icon' => 'bi-people-fill',
                'color' => '#6366f1',
                'description' => '管理系统用户和权限',
                'keywords' => ['用户', 'User', 'users', 'user'],
            ],
            'content' => [
                'title' => '内容发布',
                'icon' => 'bi-file-earmark-plus-fill',
                'color' => '#0ea5e9',
                'description' => '快速创建文章和页面',
                'keywords' => ['文章', '内容', 'Article', 'Content', 'Blog', 'category'],
            ],
            'media' => [
                'title' => '媒体管理',
                'icon' => 'bi-images',
                'color' => '#8b5cf6',
                'description' => '上传和管理媒体文件',
                'keywords' => ['媒体', '文件', '上传', 'Media', 'File', 'Upload', 'attachment'],
            ],
            'statistics' => [
                'title' => '数据统计',
                'icon' => 'bi-bar-chart-fill',
                'color' => '#22c55e',
                'description' => '查看访问和运营数据',
                'keywords' => ['统计', '分析', '报表', '日志', 'Statistics', 'Analytics', 'Log', 'Report'],
            ],
            'settings' => [
                'title' => '系统设置',
                'icon' => 'bi-gear-fill',
                'color' => '#f97316',
                'description' => '配置系统参数和选项',
                'keywords' => ['设置', '配置', '站点', 'Setting', 'Config', 'Site'],
            ],
            'menu' => [
                'title' => '菜单管理',
                'icon' => 'bi-list-ul',
                'color' => '#ef4444',
                'description' => '管理导航菜单结构',
                'keywords' => ['菜单', 'Menu', 'navigation', 'nav'],
            ],
        ];

        // 获取所有启用的菜单
        $allMenus = AdminMenu::query()
            ->where('status', 1)
            ->where('visible', 1)
            ->orderBy('sort', 'asc')
            ->get();

        $shortcuts = [];

        foreach ($shortcutRules as $key => $rule) {
            // 在所有菜单中查找匹配的菜单项
            $matchedMenu = null;
            foreach ($allMenus as $menu) {
                // 检查菜单名称或标题是否包含关键词
                foreach ($rule['keywords'] as $keyword) {
                    if (
                        stripos($menu->name, $keyword) !== false ||
                        stripos($menu->title, $keyword) !== false ||
                        stripos($menu->path ?? '', $keyword) !== false
                    ) {
                        $matchedMenu = $menu;
                        break 2;
                    }
                }

                // 如果没有找到，检查子菜单
                if ($matchedMenu) {
                    break;
                }
            }

            // 如果找到匹配的菜单，添加到快捷入口
            if ($matchedMenu) {
                $shortcuts[$key] = [
                    'title' => $rule['title'],
                    'description' => $rule['description'],
                    'icon' => $rule['icon'],
                    'color' => $rule['color'],
                    'url' => $this->getMenuUrl($matchedMenu),
                    'path' => $matchedMenu->path,
                ];
            }
        }

        return $shortcuts;
    }

    /**
     * 获取菜单的 URL
     */
    protected function getMenuUrl(AdminMenu $menu): string
    {
        // 如果有完整的 URL，直接返回
        if (!empty($menu->path) && str_starts_with($menu->path, 'http')) {
            return $menu->path;
        }

        // 构建管理后台路由
        if (!empty($menu->path)) {
            return admin_route($menu->path);
        }

        // 默认返回首页
        return admin_route('/');
    }
}

