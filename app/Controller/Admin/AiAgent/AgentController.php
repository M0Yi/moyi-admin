<?php

declare(strict_types=1);

namespace App\Controller\Admin\AiAgent;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AiAgent;
use App\Service\Admin\AiAgent\AiAgentService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Controller\AbstractController;

#[Controller(prefix: '/admin/{adminPath}/system/ai-agents')]
class AgentController extends AbstractController
{
    #[Inject]
    protected AiAgentService $service;

    #[GetMapping(path: '')]
    public function index(RequestInterface $request)
    {
        $params = $request->all();
        $params['site_id'] = $request->input('site_id');

        $result = $this->service->getList($params);

        return $this->render->render('admin.system.ai-agent.agent.index', [
            'list' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'types' => AiAgent::getTypes(),
            'statuses' => AiAgent::getStatuses(),
            'type' => $request->input('type'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
        ]);
    }

    #[GetMapping(path: 'create')]
    public function create()
    {
        return $this->render->render('admin.system.ai-agent.agent.create', [
            'types' => AiAgent::getTypes(),
            'statuses' => AiAgent::getStatuses(),
        ]);
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $agent = $this->service->getById($id);
        if (!$agent) {
            throw new BusinessException(ErrorCode::NOT_FOUND, 'Agent不存在');
        }

        return $this->render->render('admin.system.ai-agent.agent.show', [
            'agent' => $agent,
        ]);
    }

    #[GetMapping(path: '{id}/edit')]
    public function edit(int $id)
    {
        $agent = $this->service->getById($id);
        if (!$agent) {
            throw new BusinessException(ErrorCode::NOT_FOUND, 'Agent不存在');
        }

        return $this->render->render('admin.system.ai-agent.agent.edit', [
            'agent' => $agent,
            'types' => AiAgent::getTypes(),
            'statuses' => AiAgent::getStatuses(),
        ]);
    }

    #[PostMapping(path: '')]
    public function store(RequestInterface $request)
    {
        $data = $request->all();
        $data['site_id'] = Context::get('site_id');

        $agent = $this->service->create($data);

        return $this->success($agent, '创建成功');
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
