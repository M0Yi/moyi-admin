<?php

declare(strict_types=1);

namespace App\Controller\Admin\AiAgent;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Service\Admin\AiAgent\AiProviderService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Controller\AbstractController;

#[Controller(prefix: '/admin/{adminPath}/system/ai-providers')]
class ProviderController extends AbstractController
{
    #[Inject]
    protected AiProviderService $service;

    #[GetMapping(path: '')]
    public function index(RequestInterface $request)\s{
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $params = $request->all();

        $result = $this->service->getList($params);

        return $this->renderAdmin('admin.system.ai-agent.provider.index', [
            'list' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
        ]);
    }

    #[GetMapping(path: 'create')]
    public function create()
    {
        return $this->renderAdmin('admin.system.ai-agent.provider.create');
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $provider = $this->service->getById($id);
        if (!$provider) {
            throw new BusinessException(ErrorCode::NOT_FOUND, 'Provider不存在');
        }

        return $this->renderAdmin('admin.system.ai-agent.provider.show', [
            'provider' => $provider,
        ]);
    }

    #[GetMapping(path: '{id}/edit')]
    public function edit(int $id)
    {
        $provider = $this->service->getById($id);
        if (!$provider) {
            throw new BusinessException(ErrorCode::NOT_FOUND, 'Provider不存在');
        }

        return $this->renderAdmin('admin.system.ai-agent.provider.edit', [
            'provider' => $provider,
        ]);
    }

    #[PostMapping(path: '')]
    public function store(RequestInterface $request)
    {
        $data = $request->all();

        $provider = $this->service->create($data);

        return $this->success($provider, '创建成功');
    }

    #[PutMapping(path: '{id}')]
    public function update(int $id, RequestInterface $request)
    {
        $data = $request->all();

        $result = $this->service->update($id, $data);

        return $this->success($result, '更新成功');
    }

    #[DeleteMapping(path: '{id}')]
    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        return $this->success($result, '删除成功');
    }

    #[PostMapping(path: '{id}/set-default')]
    public function setDefault(int $id)
    {
        $result = $this->service->setDefault($id);

        return $this->success($result, '设置成功');
    }


    /**
     * 获取列表数据（AJAX）
     */
    public function listData(RequestInterface $request)
    {
        $params = $request->all();
        $params['site_id'] = $request->input('site_id');

        $result = $this->service->getList($params);

        return $this->success([
            'data' => $result['data'] ?? [],
            'total' => $result['total'] ?? 0,
            'page' => $result['page'] ?? 1,
            'page_size' => $result['page_size'] ?? 15,
            'last_page' => isset($result['page_size']) && $result['page_size'] > 0 
                ? (int) ceil(($result['total'] ?? 0) / $result['page_size']) 
                : 1,
        ]);
    }
}
