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

    /**
     * 获取表单字段配置
     */
    public function getFormFields(string $scene = 'create', ?AiAgentModel $agent = null): array
    {
        $typeOptions = [];
        foreach (AiAgentModel::getTypes() as $value => $label) {
            $typeOptions[] = ['value' => $value, 'label' => $label];
        }

        $fields = [
            [
                'name' => 'name',
                'label' => 'Agent 名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入 Agent 名称',
                'default' => $agent?->name ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'slug',
                'label' => 'Agent 标识',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：audit-agent',
                'default' => $agent?->slug ?? '',
                'help' => '唯一标识符，只能包含字母、数字、中划线',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'type',
                'label' => '类型',
                'type' => 'select',
                'required' => true,
                'options' => $typeOptions,
                'default' => $agent?->type ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'class',
                'label' => 'Agent 类名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：App\Service\AiAgent\AuditAgent',
                'default' => $agent?->class ?? '',
                'help' => '完整的类名，包含命名空间',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'description',
                'label' => '描述',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '请输入 Agent 描述',
                'default' => $agent?->description ?? '',
                'rows' => 3,
                'col' => 'col-12',
            ],
            [
                'name' => 'icon',
                'label' => '图标',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Bootstrap Icons 类名，例如：bi-robot',
                'default' => $agent?->icon ?? '',
                'help' => '使用 Bootstrap Icons',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'config',
                'label' => '配置 (JSON)',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '{"key": "value"}',
                'default' => $agent ? json_encode($agent->config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '',
                'rows' => 8,
                'help' => 'JSON 格式的配置信息',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'is_default',
                'label' => '是否默认',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $agent?->is_default ?? '0',
                'help' => '设为默认后，系统将优先使用此 Agent',
                'col' => 'col-12 col-md-4',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $agent?->status ?? '1',
                'col' => 'col-12 col-md-4',
            ],
            [
                'name' => 'sort',
                'label' => '排序',
                'type' => 'number',
                'required' => false,
                'placeholder' => '数字越小越靠前',
                'default' => $agent?->sort ?? 0,
                'col' => 'col-12 col-md-4',
            ],
        ];

        return $fields;
    }
}
