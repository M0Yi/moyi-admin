<?php

declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * 公共资源控制器
 *
 * 处理不需要登录的公共资源请求
 */
class PublicController extends AbstractController
{
    /**
     * 获取 favicon 图标
     *
     * 流程：
     * 1. 通过 SiteMiddleware 获取当前站点（已缓存到 Context）
     * 2. 如果站点有自定义 favicon，返回 302 重定向
     * 3. 如果没有站点或没有配置 favicon，返回 204
     *
     * @param ResponseInterface $response
     * @return PsrResponseInterface
     */
    public function favicon(ResponseInterface $response): PsrResponseInterface
    {
        // 直接使用 site() 获取当前站点（SiteMiddleware 已设置）
        $site = site();

        if (! $site || empty($site->favicon)) {
            return $response->withStatus(204);
        }

        // 返回 302 重定向到站点配置的 favicon
        return $response->redirect($site->favicon);
    }
}
