<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiAgent as AiAgentModel;
use Hyperf\Context\Context;

/**
 * AI Agent 管理服务
 */
class AiAgentService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiAgentModel::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('name', 'like', "%{$params['keyword']}%")
                    ->orWhere('slug', 'like', "%{$params['keyword']}%");
            });
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
    public function getById(int $id): ?AiAgentModel
    {
        return AiAgentModel::find($id);
    }

    /**
     * 根据 slug 获取
     */
    public function getBySlug(string $slug): ?AiAgentModel
    {
        return AiAgentModel::where('slug', $slug)->first();
    }

    /**
     * 创建
     */
    public function create(array $data): AiAgentModel
    {
        $data['site_id'] = $data['site_id'] ?? Context::get('site_id');

        return AiAgentModel::create($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool
    {
        $agent = AiAgentModel::find($id);
        if (!$agent) {
            return false;
        }

        return $agent->update($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $agent = AiAgentModel::find($id);
        if (!$agent) {
            return false;
        }

        return $agent->delete() > 0;
    }

    /**
     * 设置默认
     */
    public function setDefault(int $id): bool
    {
        $siteId = Context::get('site_id');

        // 取消其他默认
        AiAgentModel::query()
            ->where('site_id', $siteId)
            ->update(['is_default' => 0]);

        // 设置当前为默认
        $agent = AiAgentModel::find($id);
        if (!$agent) {
            return false;
        }

        return $agent->update(['is_default' => 1]);
    }

    /**
     * 获取启用的 Agent 列表
     */
    public function getActiveList(?int $siteId = null): array
    {
        $siteId = $siteId ?? Context::get('site_id');

        return AiAgentModel::query()
            ->where('status', 1)
            ->where(function ($q) use ($siteId) {
                $q->where('site_id', $siteId)
                    ->orWhereNull('site_id');
            })
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 获取类型映射
     */
    public static function getTypeOptions(): array
    {
        return AiAgentModel::getTypes();
    }

    /**
     * 获取状态映射
     */
    public static function getStatusOptions(): array
    {
        return AiAgentModel::getStatuses();
    }
}
