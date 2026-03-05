<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiAgentKnowledge;
use App\Model\Admin\AiAgentKnowledgeCategory;
use Hyperf\Context\Context;

/**
 * AI Agent 知识库服务
 */
class AiAgentKnowledgeService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiAgentKnowledge::query();

        if (!empty($params['site_id'])) {
            $query->where('site_id', $params['site_id']);
        }

        if (!empty($params['agent_id'])) {
            $query->where('agent_id', $params['agent_id']);
        }

        if (!empty($params['category_id'])) {
            $query->where('category_id', $params['category_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('title', 'like', "%{$params['keyword']}%")
                    ->orWhere('content', 'like', "%{$params['keyword']}%");
            });
        }

        $query->orderBy('sort', 'asc')
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
    public function getById(int $id): ?AiAgentKnowledge
    {
        return AiAgentKnowledge::find($id);
    }

    /**
     * 创建
     */
    public function create(array $data): AiAgentKnowledge
    {
        $data['site_id'] = $data['site_id'] ?? Context::get('site_id');

        return AiAgentKnowledge::create($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool
    {
        $knowledge = AiAgentKnowledge::find($id);
        if (!$knowledge) {
            return false;
        }

        return $knowledge->update($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $knowledge = AiAgentKnowledge::find($id);
        if (!$knowledge) {
            return false;
        }

        return $knowledge->delete() > 0;
    }

    /**
     * 获取分类列表
     */
    public function getCategories(int $agentId): array
    {
        return AiAgentKnowledgeCategory::query()
            ->where('agent_id', $agentId)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * 创建分类
     */
    public function createCategory(array $data): AiAgentKnowledgeCategory
    {
        $data['site_id'] = $data['site_id'] ?? Context::get('site_id');

        return AiAgentKnowledgeCategory::create($data);
    }

    /**
     * 删除分类
     */
    public function deleteCategory(int $id): bool
    {
        // 检查是否有文档关联
        $count = AiAgentKnowledge::where('category_id', $id)->count();
        if ($count > 0) {
            return false;
        }

        $category = AiAgentKnowledgeCategory::find($id);
        if (!$category) {
            return false;
        }

        return $category->delete() > 0;
    }
}
