<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiAgentLog;
use Hyperf\Context\Context;

/**
 * AI Agent 日志服务
 */
class AiAgentLogService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiAgentLog::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        if (!empty($params['session_id'])) {
            $query->where('session_id', $params['session_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['user_id'])) {
            $query->where('user_id', $params['user_id']);
        }

        if (!empty($params['date_start'])) {
            $query->where('created_at', '>=', $params['date_start']);
        }

        if (!empty($params['date_end'])) {
            $query->where('created_at', '<=', $params['date_end'] . ' 23:59:59');
        }

        $query->orderBy('id', 'desc');

        $page = (int)($params['page'] ?? 1);
        $pageSize = (int)($params['page_size'] ?? 15);

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
    public function getById(int $id): ?AiAgentLog
    {
        return AiAgentLog::find($id);
    }

    /**
     * 获取统计信息
     */
    public function getStatistics(array $params = []): array
    {
        $query = AiAgentLog::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        if (!empty($params['date_start'])) {
            $query->where('created_at', '>=', $params['date_start']);
        }

        if (!empty($params['date_end'])) {
            $query->where('created_at', '<=', $params['date_end'] . ' 23:59:59');
        }

        $total = $query->count();
        $success = (clone $query)->where('status', 1)->count();
        $failed = (clone $query)->where('status', 0)->count();

        $tokensQuery = (clone $query)->where('status', 1);
        $totalTokens = $tokensQuery->sum('tokens') ?? 0;

        $durationQuery = (clone $query)->where('status', 1);
        $avgDuration = $durationQuery->avg('duration') ?? 0;

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round($success / $total * 100, 2) : 0,
            'total_tokens' => $totalTokens,
            'avg_duration' => round($avgDuration),
        ];
    }
}
