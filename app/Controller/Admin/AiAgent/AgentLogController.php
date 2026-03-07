<?php

declare(strict_types=1);

namespace App\Controller\Admin\AiAgent;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Service\Admin\AiAgent\AiAgentLogService;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Controller\AbstractController;

#[Controller(prefix: '/admin/{adminPath}/system/ai-agent-logs')]
class AgentLogController extends AbstractController
{
    #[Inject]
    protected AiAgentLogService $service;

    #[GetMapping(path: '')]
    public function index(RequestInterface $request)
    {
        $params = $request->all();
        $params['site_id'] = $request->input('site_id');

        $result = $this->service->getList($params);
        $statistics = $this->service->getStatistics($params);

        return $this->render->render('admin.system.ai-agent.log.index', [
            'list' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'page_size' => $result['page_size'],
            'statistics' => $statistics,
            'agent_id' => $request->input('agent_id'),
            'status' => $request->input('status'),
            'date_start' => $request->input('date_start'),
            'date_end' => $request->input('date_end'),
        ]);
    }

    #[GetMapping(path: '{id}')]
    public function show(int $id)
    {
        $log = $this->service->getById($id);
        if (!$log) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '日志不存在');
        }

        return $this->render->render('admin.system.ai-agent.log.show', [
            'log' => $log,
        ]);
    }
}
