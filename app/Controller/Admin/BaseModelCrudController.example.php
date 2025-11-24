<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminUser;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * BaseModelCrudController 使用示例
 *
 * 这是一个示例控制器，展示如何使用 BaseModelCrudController
 * 实际使用时，请根据你的需求创建对应的控制器
 */
class ExampleUserController extends BaseModelCrudController
{
    /**
     * 指定 Model 类
     * 子类必须实现此方法
     */
    protected function getModelClass(): string
    {
        return AdminUser::class;
    }

    /**
     * 自定义验证规则
     * 子类可以重写此方法以自定义验证逻辑
     */
    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        $rules = [
            'create' => [
                'username' => 'required|string|max:50|unique:admin_users,username',
                'email' => 'required|email|unique:admin_users,email',
                'password' => 'required|string|min:6|max:20',
                'real_name' => 'nullable|string|max:50',
                'status' => 'required|in:0,1',
            ],
            'update' => [
                'username' => 'required|string|max:50|unique:admin_users,username,' . $id,
                'email' => 'required|email|unique:admin_users,email,' . $id,
                'password' => 'nullable|string|min:6|max:20',
                'real_name' => 'nullable|string|max:50',
                'status' => 'required|in:0,1',
            ],
        ];

        return $rules[$scene] ?? [];
    }

    /**
     * 自定义可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['id', 'username', 'email', 'real_name'];
    }

    /**
     * 自定义可排序字段
     */
    protected function getSortableFields(): array
    {
        return ['id', 'username', 'email', 'status', 'created_at', 'updated_at'];
    }

    /**
     * 自定义字段标签
     */
    protected function getFieldLabels(): array
    {
        return array_merge(parent::getFieldLabels(), [
            'username' => '用户名',
            'email' => '邮箱',
            'password' => '密码',
            'real_name' => '真实姓名',
            'status' => '状态',
        ]);
    }

    /**
     * 自定义列表查询（添加关联查询）
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        
        // 可以添加关联查询
        // $query->with(['roles', 'site']);
        
        return $query;
    }

    /**
     * 自定义过滤逻辑
     */
    protected function applyFilters($query, array $filters): void
    {
        // 先调用父类方法处理通用过滤
        parent::applyFilters($query, $filters);

        // 可以添加自定义过滤逻辑
        // 例如：按角色过滤
        // if (isset($filters['role_id'])) {
        //     $query->whereHas('roles', function ($q) use ($filters) {
        //         $q->where('role_id', $filters['role_id']);
        //     });
        // }
    }

    /**
     * 自定义创建页面视图
     */
    protected function renderCreatePage(RequestInterface $request): ResponseInterface
    {
        // 返回创建页面视图
        // 例如：return $this->renderAdmin('admin.users.create', []);
        return parent::renderCreatePage($request);
    }

    /**
     * 自定义编辑页面视图
     */
    protected function renderEditPage(RequestInterface $request, \App\Model\Model $model): ResponseInterface
    {
        // 返回编辑页面视图
        // 例如：return $this->renderAdmin('admin.users.edit', ['user' => $model]);
        return parent::renderEditPage($request, $model);
    }

    /**
     * 自定义列表页面视图
     */
    protected function renderListPage(RequestInterface $request): ResponseInterface
    {
        // 返回列表页面视图
        // 例如：return $this->renderAdmin('admin.users.index', []);
        return parent::renderListPage($request);
    }

    /**
     * 重写保存方法以添加自定义逻辑
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        // 可以在保存前添加自定义逻辑
        // 例如：处理密码加密、分配默认角色等
        
        // 调用父类方法执行保存
        $response = parent::store($request);
        
        // 可以在保存后添加自定义逻辑
        // 例如：记录操作日志、发送通知等
        
        return $response;
    }

    /**
     * 重写更新方法以添加自定义逻辑
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        // 可以在更新前添加自定义逻辑
        
        // 调用父类方法执行更新
        $response = parent::update($request, $id);
        
        // 可以在更新后添加自定义逻辑
        
        return $response;
    }

    /**
     * 重写删除方法以添加自定义逻辑
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        // 可以在删除前添加自定义逻辑
        // 例如：检查是否可以删除、删除关联数据等
        
        // 调用父类方法执行删除
        $response = parent::destroy($request, $id);
        
        // 可以在删除后添加自定义逻辑
        
        return $response;
    }
}

