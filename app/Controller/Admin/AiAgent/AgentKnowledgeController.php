<?php

declare(strict_types=1);

namespace App\Controller\Admin\AiAgent;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Service\Admin\AiAgent\AiAgentKnowledgeService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Controller\AbstractController;

#[Controller(prefix: '/admin/{adminPath}/system/ai-knowledge')]
class AgentKnowledgeController extends AbstractController
{
    #[Inject]
    protected AiAgentKnowledgeService $service;

    #[GetMapping(path: '')]
    public function index(RequestInterface $request)
    {
        $params = $request->all();
        $params['site_id'] = $request->input('site_id');

        $result = $this->service->getList($params);

        return $this->renderAdmin('admin.system.ai-agent.knowledge.index', [
            'list' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'agent_id' => $request->input('agent_id'),
            'category_id' => $request->input('category_id'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
        ]);
    }

    #[GetMapping(path: 'create')]
    public function create(RequestInterface $request)
    {
        return $this->renderAdmin('admin.system.ai-agent.knowledge.create', [
            'agent_id' => $request->input('agent_id'),
        ]);
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $knowledge = $this->service->getById($id);
        if (!$knowledge) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文档不存在');
        }

        return $this->renderAdmin('admin.system.ai-agent.knowledge.show', [
            'knowledge' => $knowledge,
        ]);
    }

    #[GetMapping(path: '{id}/edit')]
    public function edit(int $id)
    {
        $knowledge = $this->service->getById($id);
        if (!$knowledge) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '文档不存在');
        }

        return $this->renderAdmin('admin.system.ai-agent.knowledge.edit', [
            'knowledge' => $knowledge,
        ]);
    }

    #[PostMapping(path: '')]
    public function store(RequestInterface $request)
    {
        $data = $request->all();
        $data['site_id'] = Context::get('site_id');

        $knowledge = $this->service->create($data);

        return $this->success($knowledge, '创建成功');
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
}
