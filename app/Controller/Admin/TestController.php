<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;

/**
 * 测试控制器
 *
 * 路由通过动态路由加载器自动加载，无需手动配置
 * 路由文件位置：app/Routes/test.php
 */
class TestController extends AbstractController
{
    /**
     * 测试首页
     */
    public function index()
    {
        return $this->renderAdmin('admin.test.index1', [
            'adminMenuEnabled' => true,
        ]);
    }
}
