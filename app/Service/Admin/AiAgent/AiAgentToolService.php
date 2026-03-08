<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiAgentTool;
use Hyperf\Context\Context;

/**
 * AI Agent 工具服务
 */
class AiAgentToolService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiAgentTool::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        if (isset($params['is_enabled']) && $params['is_enabled'] !== '') {
            $query->where('is_enabled', $params['is_enabled']);
        }

        $query->orderBy('sort', 'asc')
            ->orderBy('id', 'desc');

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
    public function getById(int $id): ?AiAgentTool
    {
        return AiAgentTool::find($id);
    }

    /**
     * 创建
     */
    public function create(array $data): AiAgentTool
    {
        $data['site_id'] = $data['site_id'] ?? Context::get('site_id');

        return AiAgentTool::create($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool
    {
        $tool = AiAgentTool::find($id);
        if (!$tool) {
            return false;
        }

        return $tool->update($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $tool = AiAgentTool::find($id);
        if (!$tool) {
            return false;
        }

        return $tool->delete() > 0;
    }

    /**
     * 切换启用状态
     */
    public function toggleEnabled(int $id): bool
    {
        $tool = AiAgentTool::find($id);
        if (!$tool) {
            return false;
        }

        return $tool->update(['is_enabled' => $tool->is_enabled ? 0 : 1]);
    }
}
