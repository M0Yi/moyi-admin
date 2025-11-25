<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminUser;
use App\Model\Admin\AdminRole;
use Hyperf\DbConnection\Db;

class UserService
{
    /**
     * 获取用户列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getList(array $params = []): array
    {
        $query = AdminUser::query()
            ->with(['roles']) // 预加载角色
            ->orderBy('id', 'desc');

        // 站点过滤
        $siteId = $params['site_id'] ?? site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('username', 'like', "%{$params['keyword']}%")
                    ->orWhere('email', 'like', "%{$params['keyword']}%")
                    ->orWhere('real_name', 'like', "%{$params['keyword']}%");
            });
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
     * 获取用户详情
     *
     * @param int $id 用户ID
     * @return AdminUser
     * @throws BusinessException
     */
    public function getById(int $id): AdminUser
    {
        $query = AdminUser::query()->where('id', $id);
        
        $siteId = site_id() ?? 0;
        if ($siteId && !is_super_admin()) {
            $query->where('site_id', $siteId);
        }

        $user = $query->with(['roles'])->first();

        if (!$user) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        // 转换角色ID为数组，方便前端回显
        $user->role_ids = $user->roles->pluck('id')->toArray();

        return $user;
    }

    /**
     * 创建用户
     *
     * @param array $data
     * @return AdminUser
     */
    public function create(array $data): AdminUser
    {
        $siteId = $data['site_id'] ?? site_id() ?? 0;
        
        // 检查用户名是否存在
        if (AdminUser::where('username', $data['username'])->where('site_id', $siteId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
        }

        // 检查邮箱是否存在
        if (!empty($data['email']) && AdminUser::where('email', $data['email'])->where('site_id', $siteId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
        }

        Db::beginTransaction();
        try {
            $data['site_id'] = $siteId;
            
            // 创建用户
            $user = AdminUser::create($data);

            // 关联角色
            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            Db::commit();
            return $user;
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 更新用户
     *
     * @param int $id
     * @param array $data
     * @return AdminUser
     */
    public function update(int $id, array $data): AdminUser
    {
        $user = $this->getById($id);
        $siteId = $user->site_id;

        // 检查用户名唯一性
        if (isset($data['username']) && $data['username'] !== $user->username) {
            if (AdminUser::where('username', $data['username'])->where('site_id', $siteId)->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
            }
        }

        // 检查邮箱唯一性
        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (AdminUser::where('email', $data['email'])->where('site_id', $siteId)->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
            }
        }

        Db::beginTransaction();
        try {
            // 如果密码为空，则不更新密码
            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user->update($data);

            // 更新角色关联
            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                $user->roles()->sync($data['role_ids']);
            }

            Db::commit();
            return $user->fresh(['roles']);
        } catch (\Throwable $e) {
            Db::rollBack();
            throw $e;
        }
    }

    /**
     * 删除用户
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $user = $this->getById($id);

        // 不能删除超级管理员
        if ($user->is_admin) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无法删除超级管理员');
        }

        // 不能删除自己
        // 这里需要获取当前登录用户ID，由于在 Service 层，可以通过 Context 获取或者 Controller 传参
        // 暂时不校验删除自己，由 Controller 层或前端控制

        return $user->delete();
    }

    /**
     * 批量删除用户
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
                    // 忽略无法删除的用户
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
     * 获取角色选项
     *
     * @return array
     */
    public function getRoleOptions(): array
    {
        $siteId = site_id() ?? 0;
        $roles = AdminRole::query()
            ->where('site_id', $siteId)
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->get();

        $options = [];
        foreach ($roles as $role) {
            $options[] = [
                'value' => $role->id,
                'label' => $role->name
            ];
        }
        return $options;
    }

    /**
     * 获取表单字段配置
     *
     * @param string $scene
     * @param AdminUser|null $user
     * @return array
     */
    public function getFormFields(string $scene = 'create', ?AdminUser $user = null): array
    {
        $roleOptions = $this->getRoleOptions();

        $fields = [
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入用户名',
                'default' => $user?->username ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'real_name',
                'label' => '真实姓名',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入真实姓名',
                'default' => $user?->real_name ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'password',
                'label' => '密码',
                'type' => 'password',
                'required' => $scene === 'create', // 创建时必填，编辑时选填
                'placeholder' => $scene === 'create' ? '请输入密码' : '如果不修改密码请留空',
                'help' => $scene === 'update' ? '留空则不修改密码' : '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'email',
                'label' => '邮箱',
                'type' => 'email',
                'required' => false,
                'placeholder' => '请输入邮箱',
                'default' => $user?->email ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'mobile',
                'label' => '手机号',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入手机号',
                'default' => $user?->mobile ?? '',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'role_ids',
                'label' => '角色',
                'type' => 'checkbox', // 多选框
                'required' => false,
                'options' => $roleOptions,
                'default' => $user?->role_ids ?? [],
                'col' => 'col-12', 
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $user?->status ?? '1',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'is_admin',
                'label' => '超级管理员',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => $user?->is_admin ?? '0',
                'help' => '超级管理员拥有所有权限，慎用',
                'col' => 'col-12 col-md-6',
            ],
            [
                'name' => 'avatar',
                'label' => '头像',
                'type' => 'image',
                'required' => false,
                'default' => $user?->avatar ?? '',
                'col' => 'col-12',
            ],
        ];

        return $fields;
    }
}

