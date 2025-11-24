<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;

/**
 * 管理后台仪表盘控制器
 */
class DashboardController extends AbstractController
{
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

        return $this->renderAdmin('admin.dashboard.index', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ]);
    }
}

