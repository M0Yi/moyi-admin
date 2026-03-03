<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminPermission;

/**
 * 权限管理服务
 */
class PermissionService extends BaseService
{
    /**
     * 获取模型类名
     */
    protected function getModelClass(): string
    {
        return AdminPermission::class;
    }

    /**
     * 获取可搜索字段列表
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'slug'];
    }

    /**
     * 获取可排序字段列表
     */
    protected function getSortableFields(): array
    {
        return ['id', 'sort', 'created_at'];
    }

    /**
     * 获取默认排序字段
     */
    protected function getDefaultSortField(): string
    {
        return 'sort';
    }

    /**
     * 获取默认排序方向
     */
    protected function getDefaultSortOrder(): string
    {
        return 'asc';
    }

    /**
     * 获取权限列表（树形结构）
     */
    public function getTree(array $params = []): array
    {
        // 角色和权限已解耦站点，全局共享
        $permissions = AdminPermission::query()
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return AdminPermission::buildTree($permissions);
    }

    /**
     * 获取扁平化权限列表（带层级缩进）
     */
    public function getFlatList(array $params = []): array
    {
        // 角色和权限已解耦站点，全局共享
        $query = $this->buildQuery($params);

        if (!empty($params['keyword'])) {
            // 如果有搜索，就不按树形展示，直接列表
            return $query->get()->toArray();
        }

        $permissions = $query->get()->toArray();
        return $this->buildFlatList($permissions, 0, 0);
    }

    /**
     * 构建扁平化列表
     */
    protected function buildFlatList(array $permissions, int $parentId, int $level): array
    {
        $list = [];
        foreach ($permissions as $permission) {
            if ($permission['parent_id'] == $parentId) {
                $permission['level'] = $level;
                $permission['name_indent'] = str_repeat('　', $level) . $permission['name'];
                $list[] = $permission;

                $children = $this->buildFlatList($permissions, $permission['id'], $level + 1);
                $list = array_merge($list, $children);
            }
        }
        return $list;
    }

    /**
     * 创建权限（覆盖 BaseService）
     */
    public function create(array $data): AdminPermission
    {
        // 角色和权限已解耦站点，全局共享
        // 检查标识是否存在（全局唯一）
        if (!$this->isUnique('slug', $data['slug'])) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '权限标识已存在');
        }

        // 检查父级是否存在
        if (!empty($data['parent_id'])) {
            $parent = AdminPermission::query()
                ->where('id', $data['parent_id'])
                ->exists();
            if (!$parent) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '父级权限不存在');
            }
        }

        $data['parent_id'] = $data['parent_id'] ?? 0;

        return parent::create($data);
    }

    /**
     * 更新权限（覆盖 BaseService）
     */
    public function update(int $id, array $data): AdminPermission
    {
        $permission = $this->findOrFail($id);

        // 检查标识唯一性（全局唯一）
        if (isset($data['slug']) && $data['slug'] !== $permission->slug) {
            $query = AdminPermission::where('slug', $data['slug']);
            if ($query->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '权限标识已存在');
            }
        }

        // 检查父级是否合法（不能是自己或自己的子级）
        if (isset($data['parent_id']) && $data['parent_id'] != $permission->parent_id) {
            if ($data['parent_id'] == $id) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '不能将自己设为父级');
            }

            if ($data['parent_id'] != 0) {
                $descendantIds = $this->getDescendantIds($id);
                if (in_array($data['parent_id'], $descendantIds)) {
                    throw new BusinessException(ErrorCode::VALIDATION_ERROR, '不能将子级设为父级');
                }

                $parent = AdminPermission::query()
                    ->where('id', $data['parent_id'])
                    ->exists();
                if (!$parent) {
                    throw new BusinessException(ErrorCode::VALIDATION_ERROR, '父级权限不存在');
                }
            }
        }

        return parent::update($id, $data);
    }

    /**
     * 删除权限（覆盖 BaseService）
     */
    public function delete(int $id): bool
    {
        $permission = $this->findOrFail($id);

        // 检查是否有子权限
        if ($permission->children()->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '该权限下存在子权限，无法删除');
        }

        // 检查是否被角色使用
        if ($permission->roles()->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '该权限已被角色使用，无法删除');
        }

        return parent::delete($id);
    }

    /**
     * 批量删除权限
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;

        return $this->transaction(function () use ($ids, &$count) {
            foreach ($ids as $id) {
                try {
                    if ($this->delete((int) $id)) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    // 单个删除失败，继续处理其他
                    continue;
                }
            }
            return $count;
        }, '批量删除权限失败');
    }

    /**
     * 获取后代ID列表
     */
    protected function getDescendantIds(int $permissionId): array
    {
        $ids = [];
        $children = AdminPermission::where('parent_id', $permissionId)->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child->id));
        }

        return $ids;
    }

    /**
     * 获取父级选项
     */
    public function getParentOptions(?int $excludeId = null): array
    {
        // 角色和权限已解耦站点，全局共享
        $query = AdminPermission::query()
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        if ($excludeId) {
            $excludeIds = $this->getDescendantIds($excludeId);
            $excludeIds[] = $excludeId;
            $query->whereNotIn('id', $excludeIds);
        }

        $permissions = $query->get()->toArray();

        $options = [['value' => 0, 'label' => '顶级权限']];
        $treeOptions = $this->buildOptions($permissions, 0, 0);

        return array_merge($options, $treeOptions);
    }

    /**
     * 构建选项
     */
    protected function buildOptions(array $permissions, int $parentId, int $level): array
    {
        $options = [];
        foreach ($permissions as $permission) {
            if ($permission['parent_id'] == $parentId) {
                $prefix = str_repeat('　', $level);
                $options[] = [
                    'value' => $permission['id'],
                    'label' => $prefix . $permission['name'],
                ];

                $children = $this->buildOptions($permissions, $permission['id'], $level + 1);
                $options = array_merge($options, $children);
            }
        }
        return $options;
    }

    /**
     * 获取表单字段配置
     */
    public function getFormFields(string $scene = 'create', ?AdminPermission $permission = null): array
    {
        $parentOptions = $this->getParentOptions($permission?->id);

        $fields = [
            [
                'name' => 'parent_id',
                'label' => '父级权限',
                'type' => 'select',
                'required' => false,
                'options' => $parentOptions,
                'default' => $permission?->parent_id ?? 0,
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'type',
                'label' => '类型',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'menu', 'label' => '菜单'],
                    ['value' => 'button', 'label' => '按钮'],
                ],
                'default' => $permission?->type ?? 'menu',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'name',
                'label' => '权限名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：用户管理',
                'default' => $permission?->name ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'slug',
                'label' => '权限标识',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：system.users',
                'help' => '代码中使用的唯一标识',
                'default' => $permission?->slug ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'path',
                'label' => '请求路径',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：/system/users*',
                'help' => '后端路由路径匹配规则，支持通配符*',
                'default' => $permission?->path ?? '',
                'col' => 'col-12 col-md-8',
            ],
            [
                'name' => 'method',
                'label' => '请求方法',
                'type' => 'select',
                'required' => false,
                'options' => [
                    ['value' => '*', 'label' => '任意(*)'],
                    ['value' => 'GET', 'label' => 'GET'],
                    ['value' => 'POST', 'label' => 'POST'],
                    ['value' => 'PUT', 'label' => 'PUT'],
                    ['value' => 'DELETE', 'label' => 'DELETE'],
                    ['value' => 'PATCH', 'label' => 'PATCH'],
                ],
                'default' => $permission?->method ?? '*',
                'col' => 'col-12 col-md-4',
            ],
            [
                'name' => 'sort',
                'label' => '排序',
                'type' => 'number',
                'required' => false,
                'default' => $permission?->sort ?? 0,
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $permission?->status ?? '1',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'description',
                'label' => '描述',
                'type' => 'textarea',
                'required' => false,
                'rows' => 2,
                'default' => $permission?->description ?? '',
                'col' => 'col-12',
            ],
        ];

        return $fields;
    }
}
