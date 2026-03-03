<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminSite;
use App\Model\Admin\AdminUser;
use Hyperf\DbConnection\Db;

/**
 * 用户服务
 *
 * 继承 BaseService，提供用户相关的业务逻辑
 *
 * @package App\Service\Admin
 */
class UserService extends BaseService
{
    /**
     * 获取模型类名
     */
    protected function getModelClass(): string
    {
        return AdminUser::class;
    }

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['username', 'email', 'real_name', 'mobile'];
    }

    /**
     * 获取可排序字段
     */
    protected function getSortableFields(): array
    {
        return ['id', 'username', 'created_at', 'updated_at', 'status'];
    }

    /**
     * 获取列表数据（重写以支持预加载关联）
     */
    public function getList(array $params = [], int $pageSize = 15): array
    {
        $query = $this->buildQuery($params);

        // 预加载角色与站点
        $query->with(['roles', 'site']);

        // 精确搜索
        $searchableFields = ['username', 'real_name', 'mobile', 'email'];
        foreach ($searchableFields as $field) {
            if (!empty($params[$field])) {
                $value = trim((string) $params[$field]);
                $query->where($field, 'like', '%' . $value . '%');
            }
        }

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
     * 获取用户详情（重写以支持预加载关联）
     */
    public function find(int $id): ?AdminUser
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()->where('id', $id);

        // 站点过滤
        if ($this->hasSiteId()) {
            $siteId = site_id();
            if ($siteId && !is_super_admin()) {
                $query->where('site_id', $siteId);
            }
        }

        return $query->with(['roles'])->first();
    }

    /**
     * 创建用户
     */
    public function create(array $data): AdminUser
    {
        $siteId = $this->resolveSiteIdForWrite($data);

        // 检查用户名是否存在
        if (!$this->isUnique('username', $data['username'])) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
        }

        // 检查邮箱是否存在
        if (!empty($data['email']) && !$this->isUnique('email', $data['email'])) {
            throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
        }

        $isSuperAdmin = is_super_admin();
        if (!$isSuperAdmin && array_key_exists('role_ids', $data)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '只有超级管理员可以分配角色');
        }

        $roleIds = $isSuperAdmin ? $this->extractRoleIds($data) : null;

        return $this->transaction(function () use ($data, $siteId, $roleIds) {
            // 自动填充站点 ID
            if ($this->hasSiteId()) {
                $data['site_id'] = $siteId;
            }

            // 规范化数据
            $data = $this->normalizeNullableFields($data);

            // 过滤可填充字段
            $data = $this->filterFillable($data);

            /** @var AdminUser $user */
            $user = AdminUser::create($data);

            // 关联角色
            if ($roleIds !== null) {
                $user->roles()->sync($roleIds);
            }

            return $user;
        }, '创建用户失败');
    }

    /**
     * 更新用户
     */
    public function update(int $id, array $data): AdminUser
    {
        $user = $this->findOrFail($id);
        $siteId = $this->resolveSiteIdForUpdate($data, $user->site_id);

        // 规范化数据
        $data = $this->normalizeNullableFields($data);

        // 检查用户名唯一性
        if (isset($data['username']) && $data['username'] !== $user->username) {
            if (!$this->isUnique('username', $data['username'], $id)) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '用户名已存在');
            }
        }

        // 检查邮箱唯一性
        if (isset($data['email']) && $data['email'] !== null && $data['email'] !== $user->email) {
            if (!$this->isUnique('email', $data['email'], $id)) {
                throw new BusinessException(ErrorCode::VALIDATION_ERROR, '邮箱已存在');
            }
        }

        $isSuperAdmin = is_super_admin();
        if (!$isSuperAdmin && array_key_exists('role_ids', $data)) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '只有超级管理员可以分配角色');
        }

        $roleIds = $isSuperAdmin ? $this->extractRoleIds($data, true) : null;

        return $this->transaction(function () use ($user, $data, $siteId, $roleIds) {
            // 如果密码为空，则不更新密码
            if (empty($data['password'])) {
                unset($data['password']);
            }

            // 过滤可填充字段
            $data = $this->filterFillable($data);

            // 不能更新站点 ID（非超级管理员）
            if (!is_super_admin()) {
                unset($data['site_id']);
            } else {
                $data['site_id'] = $siteId;
            }

            // 移除不允许更新的字段
            unset($data['id'], $data['created_at']);

            $user->fill($data);
            $user->save();

            // 更新角色关联
            if ($roleIds !== null) {
                $user->roles()->sync($roleIds);
            }

            return $user->fresh(['roles']);
        }, '更新用户失败');
    }

    /**
     * 删除用户
     */
    public function delete(int $id): bool
    {
        $user = $this->findOrFail($id);

        // 不能删除超级管理员
        if ($user->is_admin) {
            throw new BusinessException(ErrorCode::FORBIDDEN, '无法删除超级管理员');
        }

        return $user->delete();
    }

    /**
     * 批量删除用户
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
                    // 忽略无法删除的用户
                    continue;
                }
            }
            return $count;
        }, '批量删除用户失败');
    }

    /**
     * 获取角色选项
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
                'required' => $scene === 'create',
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
     */
    private function normalizeNullableFields(array $data): array
    {
        $nullableFields = ['email', 'mobile'];

        foreach ($nullableFields as $field) {
            if (isset($data[$field]) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * 从数据中提取角色ID，并移除 role_ids 字段
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
            static fn($id) => (int) $id,
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

        if (!is_super_admin()) {
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

        if (!$exists) {
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
