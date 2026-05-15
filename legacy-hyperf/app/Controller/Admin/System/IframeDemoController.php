<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use Psr\Http\Message\ResponseInterface;

class IframeDemoController extends AbstractController
{
    /**
     * 体验中心首页
     */
    public function index(): ResponseInterface
    {
        return $this->renderAdmin('admin.system.iframe-demo.index', [
            'featureHighlights' => $this->getFeatureHighlights(),
            'diagnostics' => $this->buildDiagnostics(),
        ]);
    }

    /**
     * Shell 弹窗示例页面
     */
    public function modalDemo(): ResponseInterface
    {
        return $this->renderAdmin('admin.system.iframe-demo.modal-demo', [
            'formDefaults' => $this->getModalFormDefaults(),
            'diagnostics' => $this->buildDiagnostics(),
        ]);
    }

    private function getFeatureHighlights(): array
    {
        return [
            [
                'icon' => 'bi bi-layout-sidebar-inset',
                'title' => '统一标签页路由',
                'description' => 'Admin 标签管理为所有页面追加 _embed 参数，刷新/关闭行为保持一致。',
            ],
            [
                'icon' => 'bi bi-arrows-fullscreen',
                'title' => 'Iframe Shell 弹窗',
                'description' => '通过 data-iframe-shell-* 属性即可一键把任意页面包装成弹窗流程。',
            ],
            [
                'icon' => 'bi bi-collection',
                'title' => '多标签通信',
                'description' => '内页通过 AdminIframeClient.success/notify/close 与父级标签交互。',
            ],
            [
                'icon' => 'bi bi-hdd-network',
                'title' => '标准化 URL',
                'description' => 'renderAdmin() 自动注入 normalizedUrl，帮助父级去重与定位标签。',
            ],
        ];
    }

    private function buildDiagnostics(): array
    {
        $queryParams = $this->request->getQueryParams();
        $serverParams = $this->request->getServerParams();

        return [
            'query' => $queryParams,
            'channel' => $queryParams['_channel'] ?? null,
            'sec_fetch_dest' => $serverParams['HTTP_SEC_FETCH_DEST'] ?? null,
        ];
    }

    private function getModalFormDefaults(): array
    {
        return [
            'table_name' => 'admin_users',
            'module_name' => 'System',
            'model_name' => 'AdminUser',
            'db_connection' => 'default',
            'channel' => $this->request->getQueryParams()['_channel'] ?? 'iframe-demo-modal',
        ];
    }
}


