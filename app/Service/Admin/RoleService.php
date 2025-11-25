<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminPermission;
use App\Model\Admin\AdminUser;
use Hyperf\DbConnection\Db;

class RoleService
{
    /**
     * 获取角色列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminRole::query()
            ->with(['permissions']) // 预加载权限
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        // 站点过滤
        $siteId = $params['site_id'] ?? site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $query->where('name', 'like', "%{$params['keyword']}%")
                ->orWhere('slug', 'like', "%{$params['keyword']}%");
        }

        // 状态筛选
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', $params['status']);
        }

        // 分页
        $pageSize = $params['page_size'] ?? 15;
        $paginator = $query->paginate((int)$pageSize);

        return [
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    /**
     * 获取角色详情
     *
     * @param int $id
     * @return AdminRole
     * @throws BusinessException
     */
    public function getById(int $id): AdminRole
    {
        $query = AdminRole::query()->where('id', $id);
        
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $role = $query->with(['permissions'])->first();

        if (!$role) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '角色不存在');
        }

        // 转换权限ID为数组
        $role->permission_ids = $role->permissions->pluck('id')->toArray();

        return $role;
    }

    /**
     * 创建角色
     *
     * @param array $data
     * @return AdminRole
     */
    public function create(array $data): AdminRole
    {
        $siteId = $data['site_id'] ?? site_id() ?? 0;
        
        // 检查标识是否存在
        if (AdminRole::where('slug', $data['slug'])->where('site_id', $siteId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '角色标识已存在');
        }

        Db::beginTransaction();
        try {
            $data['site_id'] = $siteId;
            
            // 创建角色
            $role = AdminRole::create($data);

            // 关联权限
            if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            Db::commit();
            return $role;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 更新角色
     *
     * @param int $id
     * @param array $data
     * @return AdminRole
     */
    public function update(int $id, array $data): AdminRole
    {
        $role = $this->getById($id);
        $siteId = $role->site_id;

        // 检查标识唯一性
        if (isset($data['slug']) && $data['slug'] !== $role->slug) {
            if (AdminRole::where('slug', $data['slug'])->where('site_id', $siteId)->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '角色标识已存在');
            }
        }

        Db::beginTransaction();
        try {
            $role->update($data);

            // 更新权限关联
            if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                $role->permissions()->sync($data['permission_ids']);
            }

            Db::commit();
            return $role->fresh(['permissions']);
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 删除角色
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $role = $this->getById($id);

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
     *
     * @param array $ids
     * @return int
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        Db::beginTransaction();
        try {
            foreach ($ids as $id) {
                try {
                    if ($this->delete((int)$id)) {
                        $count++;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            Db::commit();
            return $count;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 获取权限选项（树形结构）
     *
     * @return array
     */
    public function getPermissionOptions(): array
    {
        $siteId = site_id() ?? 0;
        $permissions = AdminPermission::query()
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        return $this->buildPermissionTreeOptions($permissions);
    }

    /**
     * 构建权限树形选项（带层级缩进）
     *
     * @param array $permissions
     * @param int $parentId
     * @param int $level
     * @return array
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
     * 获取表单字段配置
     *
     * @param string $scene
     * @param AdminRole|null $role
     * @return array
     */
    public function getFormFields(string $scene = 'create', ?AdminRole $role = null): array
    {
        $permissionOptions = $this->getPermissionOptions();

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
                'type' => 'checkbox', // 多选框，未来可以改为树形选择组件
                'required' => false,
                'options' => $permissionOptions,
                'default' => $role?->permission_ids ?? [],
                'col' => 'col-12',
            ],
        ];

        return $fields;
    }
}

