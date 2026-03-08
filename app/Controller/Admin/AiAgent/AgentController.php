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
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        return $this->renderAdmin('admin.system.ai-agent.agent.index');
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

    #[GetMapping(path: 'create')]
    public function create()
    {
        return $this->renderAdmin('admin.system.ai-agent.agent.create', [
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

        return $this->renderAdmin('admin.system.ai-agent.agent.show', [
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

        return $this->renderAdmin('admin.system.ai-agent.agent.edit', [
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
