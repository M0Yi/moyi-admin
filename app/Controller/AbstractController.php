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

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\View\RenderInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\SessionInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;


abstract class AbstractController
{

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;
    #[Inject]

    protected SessionInterface $session;

    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    #[Inject]
    protected RenderInterface $render;

    protected function result(string $msg = '', array $data = null, int $code = 0,array $extra = []): \Psr\Http\Message\ResponseInterface
    {

        return $this->response->json(array_merge([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ],$extra));
    }


    protected function error(string $msg = 'error', array|null $data = null, int $code = 400): \Psr\Http\Message\ResponseInterface
    {
        return $this->result($msg, $data, $code);
    }

    protected function success(array|null $data = null, string $msg = '', int $code = 200): \Psr\Http\Message\ResponseInterface
    {
        return $this->result($msg, $data, $code);
    }

    /**
     * 检测是否为 iframe 模式
     * 
     * 检测方式（按优先级）：
     * 1. URL 参数 _embed=1（最可靠，由 tab-manager.js 自动添加）
     * 2. HTTP_SEC_FETCH_DEST 请求头（浏览器自动设置）
     */
    protected function isEmbedded(): bool
    {
        // 方式1：检查 URL 参数 _embed
        $queryParams = $this->request->getQueryParams();
        if (isset($queryParams['_embed']) && $queryParams['_embed'] === '1') {
            return true;
        }

        // 方式2：检查 HTTP_SEC_FETCH_DEST 请求头（现代浏览器支持）
        $serverParams = $this->request->getServerParams();
        if (isset($serverParams['HTTP_SEC_FETCH_DEST']) && $serverParams['HTTP_SEC_FETCH_DEST'] === 'iframe') {
            return true;
        }

        return false;
    }

    /**
     * 渲染 Admin 视图（自动注入 iframe 模式变量）
     * 
     * @param string $view 视图路径，例如 'admin.dashboard.index'
     * @param array $data 传递给视图的数据
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function renderAdmin(string $view, array $data = []): \Psr\Http\Message\ResponseInterface
    {
        // 自动注入 iframe 模式变量
        $data['isEmbedded'] = $this->isEmbedded();

        // 构建标准化 URL（用于标签页管理器，移除 _embed 参数）
        $requestUri = $this->request->getUri()->getPath();
        $queryParams = $this->request->getQueryParams();
        
        // 移除 _embed 参数，避免在 URL 中显示
        if (isset($queryParams['_embed'])) {
            unset($queryParams['_embed']);
        }

        $normalizedUrl = $requestUri;
        if (!empty($queryParams)) {
            $normalizedUrl .= '?' . http_build_query($queryParams);
        }

        if ($normalizedUrl === '') {
            $normalizedUrl = '/admin';
        }

        $data['normalizedUrl'] = $normalizedUrl;

        return $this->render->render($view, $data);
    }


}
