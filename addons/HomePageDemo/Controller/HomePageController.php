<?php

declare(strict_types=1);

namespace Addons\HomePageDemo\Controller;

use App\Controller\AbstractController;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\ViewEngine\view;

/**
 * 首页控制器
 *
 * 此控制器演示如何通过插件替换默认首页
 */
class HomePageController extends AbstractController
{
    /**
     * 首页显示
     *
     * 替换默认的首页内容
     */
    public function index(RenderInterface $render): ResponseInterface
    {
        // 获取插件配置
        $demoTitle = addons_config('HomePageDemo', 'demo_title') ?? '插件首页演示';
        $demoDescription = addons_config('HomePageDemo', 'demo_description') ?? '这是通过插件系统替换的首页内容';

        // 获取当前站点信息
        $currentSite = site();

        // 构建页面数据
        $data = [
            'title' => $demoTitle,
            'description' => $demoDescription,
            'current_time' => date('Y-m-d H:i:s'),
            'site_name' => $currentSite ? $currentSite->name : '未配置站点',
            'plugin_info' => [
                'name' => 'HomePageDemo',
                'version' => '1.0.0',
                'description' => '首页替换演示插件',
            ],
            'features' => [
                '插件化架构' => '支持通过插件扩展系统功能',
                '首页替换' => '插件可以替换默认首页',
                '配置管理' => '支持后台配置管理',
                '视图渲染' => '使用 Blade 模板引擎',
            ],
        ];

        // 渲染视图
        return $render->render('addons.homepage_demo.index', $data);
    }

    /**
     * API 接口示例
     *
     * 返回插件信息
     */
    public function api(): ResponseInterface
    {
        return $this->success([
            'plugin' => 'HomePageDemo',
            'version' => '1.0.0',
            'message' => '这是插件提供的 API 接口',
            'timestamp' => time(),
        ], '获取插件信息成功');
    }
}
