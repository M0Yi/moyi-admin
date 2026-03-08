<?php

declare(strict_types=1);

namespace App\Controller\Admin\AiAgent;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Service\Admin\AiAgent\AiAgentSessionService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Controller\AbstractController;

#[Controller(prefix: '/admin/{adminPath}/system/ai-agent-sessions')]
class AgentSessionController extends AbstractController
{
    #[Inject]
    protected AiAgentSessionService $service;

    #[GetMapping(path: '')]
    public function index(RequestInterface $request)
    {
        $params = $request->all();
        $params['site_id'] = $request->input('site_id');

        $result = $this->service->getList($params);

        return $this->renderAdmin('admin.system.ai-agent.session.index', [
            'list' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'agent_id' => $request->input('agent_id'),
            'status' => $request->input('status'),
            'user_type' => $request->input('user_type'),
        ]);
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $session = $this->service->getById($id);
        if (!$session) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '会话不存在');
        }

        return $this->renderAdmin('admin.system.ai-agent.session.show', [
            'session' => $session,
        ]);
    }

    #[PostMapping(path: '{id}/end')]
    public function end(int $id)
    {
        $session = $this->service->getById($id);
        if (!$session) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '会话不存在');
        }

        $result = $this->service->update($id, ['status' => 0]);

        return $this->success($result, '会话已结束');
    }

    #[DeleteMapping(path: '{id}')]
    public function destroy(int $id)
    {
        $result = $this->service->delete($id);

        return $this->success($result, '删除成功');
    }
}
