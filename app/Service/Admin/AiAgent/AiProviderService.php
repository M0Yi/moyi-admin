<?php

declare(strict_types=1);

namespace App\Service\Admin\AiAgent;

use App\Model\Admin\AiProvider as AiProviderModel;

/**
 * AI Provider 管理服务
 */
class AiProviderService
{
    /**
     * 获取列表
     */
    public function getList(array $params = []): array
    {
        $query = AiProviderModel::query();

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
    public function getById(int $id): ?AiProviderModel
    {
        return AiProviderModel::find($id);
    }

    /**
     * 根据 slug 获取
     */
    public function getBySlug(string $slug): ?AiProviderModel
    {
        return AiProviderModel::where('slug', $slug)->first();
    }

    /**
     * 创建
     */
    public function create(array $data): AiProviderModel
    {
        return AiProviderModel::create($data);
    }

    /**
     * 更新
     */
    public function update(int $id, array $data): bool
    {
        $provider = AiProviderModel::find($id);
        if (!$provider) {
            return false;
        }

        return $provider->update($data);
    }

    /**
     * 删除
     */
    public function delete(int $id): bool
    {
        $provider = AiProviderModel::find($id);
        if (!$provider) {
            return false;
        }

        return $provider->delete() > 0;
    }

    /**
     * 设置默认
     */
    public function setDefault(int $id): bool
    {
        // 取消其他默认
        AiProviderModel::query()->update(['is_default' => 0]);

        // 设置当前为默认
        $provider = AiProviderModel::find($id);
        if (!$provider) {
            return false;
        }

        return $provider->update(['is_default' => 1]);
    }

    /**
     * 获取默认 Provider
     */
    public function getDefault(): ?AiProviderModel
    {
        return AiProviderModel::where('is_default', 1)
            ->where('status', 1)
            ->first();
    }

    /**
     * 获取启用的 Provider 列表
     */
    public function getActiveList(): array
    {
        return AiProviderModel::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->get()
            ->toArray();
    }
}
