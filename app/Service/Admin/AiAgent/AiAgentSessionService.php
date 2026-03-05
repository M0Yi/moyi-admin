<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiAgentSession;
use Hyperf\Context\Context;

/**
 * AI Agent 会话服务
 */
class AiAgentSessionService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiAgentSession::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['user_type'])) {
            $query->where('user_type', $params['user_type']);
        }

        $query->orderBy('last_message_at', 'desc')
            ->orderBy('id', 'desc');

        $page = $params['page'] ?? 1;
        $pageSize = $params['page_size'] ?? 15;

        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * 获取详情
     */
    public function getById(int $id): ?AiAgentSession
    {
        return AiAgentSession::find($id);
    }

    /**
     * 根据会话ID获取
     */
    public function findBySessionId(string $sessionId): ?AiAgentSession
    {
        return AiAgentSession::where('session_id', $sessionId)->first();
    }

    /**
     * 创建
     */
    public function create(array $data): AiAgentSession
    {
        $data['site_id'] = $data['site_id'] ?? Context::get('site_id');

        return AiAgentSession::create($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool
    {
        $session = AiAgentSession::find($id);
        if (!$session) {
            return false;
        }

        return $session->update($data);
    }

    /**
     * 根据会话ID更新
     */
    public function updateBySessionId(string $sessionId, array $data): bool
    {
        $session = AiAgentSession::where('session_id', $sessionId)->first();
        if (!$session) {
            return false;
        }

        return $session->update($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $session = AiAgentSession::find($id);
        if (!$session) {
            return false;
        }

        return $session->delete() > 0;
    }

    /**
     * 清理过期会话
     */
    public function cleanExpired(string $expiredAt): int
    {
        return AiAgentSession::where('status', 0)
            ->where('updated_at', '<', $expiredAt)
            ->delete();
    }

    /**
     * 结束会话
     */
    public function endSession(string $sessionId): bool
    {
        return $this->updateBySessionId($sessionId, ['status' => 0]);
    }
}
