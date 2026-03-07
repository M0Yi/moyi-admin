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
    public function index(RequestInterface $request)
    {
        $params = $request->all();

        $result = $this->service->getList($params);

        return $this->render->render('admin.system.ai-agent.provider.index', [
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
        return $this->render->render('admin.system.ai-agent.provider.create');
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $provider = $this->service->getById($id);
        if (!$provider) {
            throw new BusinessException(ErrorCode::NOT_FOUND, 'Provider不存在');
        }

        return $this->render->render('admin.system.ai-agent.provider.show', [
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

        return $this->render->render('admin.system.ai-agent.provider.edit', [
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
}
