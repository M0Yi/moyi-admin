<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminPermission;
use App\Model\Admin\AdminRole;
use Hyperf\DbConnection\Db;

/**
 * 角色服务
 *
 * 继承 BaseService，提供角色相关的业务逻辑
 *
 * 注意：角色已解耦站点，全局共享
 *
 * @package App\Service\Admin
 */
class RoleService extends BaseService
{
    /**
     * 获取模型类名
     */
    protected function getModelClass(): string
    {
        return AdminRole::class;
    }

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'slug'];
    }

    /**
     * 获取可排序字段
     */
    protected function getSortableFields(): array
    {
        return ['id', 'sort', 'name', 'created_at'];
    }

    /**
     * 获取默认排序字段
     */
    protected function getDefaultSortField(): string
    {
        return 'sort';
    }

    /**
     * 获取列表数据（重写以支持预加载关联）
     */
    public function getList(array $params = [], int $pageSize = 15): array
    {
        $query = $this->buildQuery($params);

        // 预加载权限
        $query->with(['permissions']);

        $page = (int) ($params['page'] ?? 1);
        $pageSize = (int) ($params['page_size'] ?? $pageSize);
        $paginator = $query->paginate($pageSize, ['*'], 'page', $page);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取角色详情（重写以支持预加载关联）
     */
    public function find(int $id): ?AdminRole
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->where('id', $id);

        // 预加载权限
        $query->with(['permissions']);

        /** @var AdminRole|null $role */
        $role = $query->first();

        if ($role) {
            // 转换权限ID为数组
            $role->permission_ids = $role->permissions->pluck('id')->toArray();
        }

        return $role;
    }

    /**
     * 创建角色
     */
    public function create(array $data): AdminRole
    {
        // 检查标识是否存在（全局唯一）
        if (AdminRole::where('slug', $data['slug'])->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '角色标识已存在');
        }

        return $this->transaction(function () use ($data) {
            // 提取权限ID（permission_ids 不在 fillable 中，需要单独处理）
            $hasPermissionField = array_key_exists('permission_ids', $data);
            $permissionIds = $hasPermissionField ? $data['permission_ids'] : [];
            unset($data['permission_ids']);

            // 创建角色（只包含 fillable 中的字段）
            $data = $this->filterFillable($data);
            /** @var AdminRole $role */
            $role = AdminRole::create($data);

            // 关联权限
            if ($hasPermissionField) {
                $role->permissions()->sync(is_array($permissionIds) ? $permissionIds : []);
            }

            return $role;
        }, '创建角色失败');
    }

    /**
     * 更新角色
     */
    public function update(int $id, array $data): AdminRole
    {
        $role = $this->findOrFail($id);

        // 检查标识唯一性（全局唯一）
        if (isset($data['slug']) && $data['slug'] !== $role->slug) {
            if (AdminRole::where('slug', $data['slug'])->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '角色标识已存在');
            }
        }

        return $this->transaction(function () use ($role, $data) {
            // 提取权限ID（permission_ids 不在 fillable 中，需要单独处理）
            $hasPermissionField = array_key_exists('permission_ids', $data);
            $permissionIds = $hasPermissionField ? $data['permission_ids'] : [];
            unset($data['permission_ids']);

            // 从模型 attributes 中移除 permission_ids
            $role->offsetUnset('permission_ids');

            // 过滤可填充字段
            $data = $this->filterFillable($data);

            // 更新角色基本信息
            $role->update($data);

            // 更新权限关联
            if ($hasPermissionField) {
                $role->permissions()->sync(is_array($permissionIds) ? $permissionIds : []);
            }

            return $role->fresh(['permissions']);
        }, '更新角色失败');
    }

    /**
     * 删除角色
     */
    public function delete(int $id): bool
    {
        $role = $this->findOrFail($id);

        // 检查是否被用户使用
        if ($role->users()->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '该角色下存在用户，无法删除');
        }

        // 超级管理员角色不能删除
        if ($role->slug === 'super-admin') {
            throw new BusinessException(ErrorCode::FORBIDDEN, '超级管理员角色无法删除');
        }

        return $role->delete();
    }

    /**
     * 批量删除角色
     */
    public function batchDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        return $this->transaction(function () use ($ids) {
            $count = 0;
            foreach ($ids as $id) {
                try {
                    if ($this->delete((int) $id)) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    // 忽略无法删除的角色
                    continue;
                }
            }
            return $count;
        }, '批量删除角色失败');
    }

    /**
     * 获取权限选项（树形结构）
     */
    public function getPermissionOptions(): array
    {
        $permissions = AdminPermission::query()
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return $this->buildPermissionTreeOptions($permissions);
    }

    /**
     * 构建权限树形选项（带层级缩进）
     */
    protected function buildPermissionTreeOptions(array $permissions, int $parentId = 0, int $level = 0): array
    {
        $options = [];

        foreach ($permissions as $permission) {
            if ($permission['parent_id'] == $parentId) {
                $prefix = str_repeat('　', $level);
                $options[] = [
                    'value' => $permission['id'],
                    'label' => $prefix . $permission['name'] . ($permission['type'] === 'menu' ? ' [菜单]' : ' [按钮]'),
                ];

                $children = $this->buildPermissionTreeOptions($permissions, $permission['id'], $level + 1);
                $options = array_merge($options, $children);
            }
        }

        return $options;
    }

    /**
     * 获取权限树（仅启用状态）
     */
    public function getPermissionTree(): array
    {
        $permissions = AdminPermission::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return AdminPermission::buildTree($permissions);
    }

    /**
     * 获取表单字段配置
     */
    public function getFormFields(string $scene = 'create', ?AdminRole $role = null): array
    {
        $permissionOptions = $this->getPermissionOptions();
        $permissionTree = $this->getPermissionTree();

        $permissionTypeMap = [
            'menu' => [
                'label' => '菜单',
                'class' => 'badge-menu',
            ],
            'button' => [
                'label' => '按钮',
                'class' => 'badge-button',
            ],
        ];

        $fields = [
            [
                'name' => 'name',
                'label' => '角色名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：普通管理员',
                'default' => $role?->name ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'slug',
                'label' => '角色标识',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：admin',
                'help' => '用于代码中判断角色，唯一',
                'default' => $role?->slug ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'sort',
                'label' => '排序',
                'type' => 'number',
                'required' => false,
                'default' => $role?->sort ?? 0,
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $role?->status ?? '1',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'description',
                'label' => '描述',
                'type' => 'textarea',
                'required' => false,
                'rows' => 3,
                'default' => $role?->description ?? '',
                'col' => 'col-12',
            ],
            [
                'name' => 'permission_ids',
                'label' => '权限分配',
                'type' => 'permission_tree',
                'required' => false,
                'options' => $permissionOptions,
                'treeData' => $permissionTree,
                'typeMap' => $permissionTypeMap,
                'default' => $role?->permission_ids ?? [],
                'col' => 'col-12',
            ],
        ];

        return $fields;
    }
}
