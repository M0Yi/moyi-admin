<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminSite;
use App\Model\Admin\AdminUser;
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
            ->with(['roles', 'site']) // 预加载角色与站点
            ->orderBy('id', 'desc');

        // 站点过滤：普通管理员固定当前站点，超级管理员可根据筛选选择站点
        $requestedSiteId = isset($params['site_id']) ? (int) $params['site_id'] : 0;
        $currentSiteId = (int) (site_id() ?? 0);
        if (is_super_admin()) {
            if ($requestedSiteId > 0) {
                $query->where('site_id', $requestedSiteId);
            }
        } elseif ($currentSiteId > 0) {
            $query->where('site_id', $currentSiteId);
        }

        // 关键词搜索
        if (!empty($params['keyword'])) {
            $keyword = trim((string) $params['keyword']);
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('real_name', 'like', "%{$keyword}%")
                    ->orWhere('mobile', 'like', "%{$keyword}%");
            });
        }

        // 精确搜索
        $searchableFields = ['username', 'real_name', 'mobile', 'email'];
        foreach ($searchableFields as $field) {
            if (!empty($params[$field])) {
                $value = trim((string) $params[$field]);
                $query->where($field, 'like', "%{$value}%");
            }
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
        $siteId = $this->resolveSiteIdForWrite($data);
        
        // 检查用户名是否存在
        if (AdminUser::where('username', $data['username'])->where('site_id', $siteId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
        }

        // 检查邮箱是否存在
        if (!empty($data['email']) && AdminUser::where('email', $data['email'])->where('site_id', $siteId)->exists()) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
        }

        $isSuperAdmin = is_super_admin();
        if (! $isSuperAdmin && array_key_exists('role_ids', $data)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '只有超级管理员可以分配角色');
        }

        $roleIds = $isSuperAdmin ? $this->extractRoleIds($data) : null;

        Db::beginTransaction();
        try {
            $data['site_id'] = $siteId;
            
            // 将空字符串转换为 NULL，避免唯一约束冲突
            $data = $this->normalizeNullableFields($data);
            $data = $this->filterFillableFields($data);
            
            // 创建用户
            $user = AdminUser::create($data);

            // 关联角色
            if ($roleIds !== null) {
                $user->roles()->sync($roleIds);
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
        $siteId = $this->resolveSiteIdForUpdate($data, $user->site_id);

        // 先将空字符串转换为 NULL，避免唯一约束冲突
        // 需要在唯一性检查之前进行规范化，确保检查的是规范化后的值
        $data = $this->normalizeNullableFields($data);

        // 检查用户名唯一性
        if (isset($data['username']) && $data['username'] !== $user->username) {
            if (AdminUser::where('username', $data['username'])->where('site_id', $siteId)->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
            }
        }

        // 检查邮箱唯一性（NULL 值不违反唯一约束，所以不需要检查 NULL 的唯一性）
        if (isset($data['email']) && $data['email'] !== null && $data['email'] !== $user->email) {
            if (AdminUser::where('email', $data['email'])->where('site_id', $siteId)->where('id', '!=', $id)->exists()) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
            }
        }

        $isSuperAdmin = is_super_admin();
        if (! $isSuperAdmin && array_key_exists('role_ids', $data)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '只有超级管理员可以分配角色');
        }

        $roleIds = $isSuperAdmin ? $this->extractRoleIds($data, true) : null;

        Db::beginTransaction();
        try {
            // 如果密码为空，则不更新密码
            if (empty($data['password'])) {
                unset($data['password']);
            }

            // 过滤可更新字段，避免非法字段导致更新失败
            $data = $this->filterFillableFields($data);

            if (! is_super_admin()) {
                unset($data['site_id']);
            } else {
                $data['site_id'] = $siteId;
            }

            $user->update($data);

            // 更新角色关联
            if ($roleIds !== null) {
                $user->roles()->sync($roleIds);
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
        $roles = AdminRole::query()
            ->where('status', 1)
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc')
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
        $isSuperAdmin = is_super_admin();
        $siteOptions = $isSuperAdmin ? $this->getSiteOptions() : [];
        $defaultSiteId = (int) ($user?->site_id ?? site_id() ?? ($siteOptions[0]['value'] ?? 0));
        $roleOptions = $isSuperAdmin ? $this->getRoleOptions() : [];

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

        if ($isSuperAdmin) {
            array_splice($fields, 1, 0, [[
                'name' => 'site_id',
                'label' => '站点',
                'type' => 'select',
                'required' => true,
                'options' => $siteOptions,
                'default' => (string) $defaultSiteId,
                'help' => '超级管理员可为用户指定所属站点',
                'col' => 'col-12 col-md-6',
            ]]);
        }

        if ($isSuperAdmin) {
            $roleField = [
                'name' => 'role_ids',
                'label' => '角色',
                'type' => 'checkbox',
                'required' => false,
                'options' => $roleOptions,
                'default' => $user?->role_ids ?? [],
                'col' => 'col-12',
            ];

            $fieldNames = array_column($fields, 'name');
            $statusIndex = array_search('status', $fieldNames, true);
            if ($statusIndex === false) {
                $fields[] = $roleField;
            } else {
                array_splice($fields, $statusIndex, 0, [$roleField]);
            }
        }

        return $fields;
    }

    /**
     * 获取站点筛选选项
     */
    public function getSiteFilterOptions(): array
    {
        return $this->getSiteOptions();
    }

    /**
     * 规范化可空字段：将空字符串转换为 NULL
     * 避免数据库唯一约束冲突（NULL 不违反唯一约束，但空字符串会）
     *
     * @param array $data
     * @return array
     */
    private function normalizeNullableFields(array $data): array
    {
        // 可空字段列表：这些字段在数据库中允许为 NULL，且有唯一约束
        $nullableFields = ['email', 'mobile'];

        foreach ($nullableFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * 过滤可批量赋值的字段，避免意外字段导致更新失败
     */
    private function filterFillableFields(array $data): array
    {
        static $fillableMap = null;

        if ($fillableMap === null) {
            $fillable = (new AdminUser())->getFillable();
            $fillableMap = array_flip($fillable);
        }

        if (empty($fillableMap)) {
            return $data;
        }

        return array_intersect_key($data, $fillableMap);
    }

    /**
     * 从数据中提取角色ID，并移除 role_ids 字段
     *
     * @return array<int>|null 返回 null 表示未提交角色信息
     */
    private function extractRoleIds(array &$data, bool $emptyWhenMissing = false): ?array
    {
        if (!array_key_exists('role_ids', $data)) {
            return $emptyWhenMissing ? [] : null;
        }

        $roleIds = $data['role_ids'];
        unset($data['role_ids']);

        if (!is_array($roleIds)) {
            return [];
        }

        $roleIds = array_map(
            static fn($id) => (int)$id,
            array_filter(
                $roleIds,
                static fn($id) => $id !== null && $id !== ''
            )
        );

        return array_values(array_unique($roleIds));
    }

    private function resolveSiteIdForWrite(array &$data): int
    {
        if (is_super_admin() && isset($data['site_id']) && $data['site_id'] !== '') {
            $siteId = (int) $data['site_id'];
            $this->assertSiteExists($siteId);
        } else {
            $siteId = site_id() ?? 0;
        }

        if (! is_super_admin()) {
            unset($data['site_id']);
        }

        return $siteId;
    }

    private function resolveSiteIdForUpdate(array &$data, int $currentSiteId): int
    {
        if (is_super_admin() && array_key_exists('site_id', $data) && $data['site_id'] !== '') {
            $siteId = (int) $data['site_id'];
            $this->assertSiteExists($siteId);
            return $siteId;
        }

        $data['site_id'] = $currentSiteId;
        return $currentSiteId;
    }

    private function assertSiteExists(int $siteId): void
    {
        if ($siteId <= 0) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '请选择站点');
        }

        $exists = AdminSite::query()
            ->where('id', $siteId)
            ->where('status', AdminSite::STATUS_ENABLED)
            ->exists();

        if (! $exists) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '站点不存在或已停用');
        }
    }

    private function getSiteOptions(): array
    {
        return AdminSite::query()
            ->where('status', AdminSite::STATUS_ENABLED)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static function (AdminSite $site) {
                return [
                    'value' => (string) $site->id,
                    'label' => $site->name . " (#{$site->id})",
                ];
            })
            ->toArray();
    }
}

