<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminPermission;
use App\Service\Admin\PermissionService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
// composer require hyperf/paginator
class PermissionController extends BaseModelCrudController
{
    #[Inject]
    protected PermissionService $permissionService;

    protected function getModelClass(): string
    {
        return AdminPermission::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [
            'create' => [
                'name' => 'required|string|max:50',
                'slug' => 'required|string|max:100',
                'type' => 'required|in:menu,button',
            ],
            'update' => [
                'name' => 'required|string|max:50',
                'slug' => 'required|string|max:100',
                'type' => 'required|in:menu,button',
            ],
        ][$scene] ?? [];
    }

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        // 如果是 AJAX 请求，返回 JSON 数据（支持 _ajax=1 和 format=json）
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        return $this->renderAdmin('admin.system.permission.index');
    }

    /**
     * 获取列表数据
     * 重写以支持树形扁平化展示
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $data = $this->permissionService->getList($params);
        
        // 扁平化列表不需要 total 等分页信息，直接返回 data
        return $this->success([
            'data' => $data,
            'total' => count($data),
            'page' => 1,
            'page_size' => count($data),
            'last_page' => 1,
        ]);
    }

    /**
     * 创建页面
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        $fields = $this->permissionService->getFormFields('create');
        $formSchema = [
            'title' => '新增权限',
            'fields' => $fields,
            'submitUrl' => admin_route('system/permissions'),
            'method' => 'POST',
            'redirectUrl' => admin_route('system/permissions'),
        ];

        return $this->renderAdmin('admin.system.permission.create', [
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 保存数据
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        $data = $request->all();
        $this->validateData($data, 'create');
        
        $permission = $this->permissionService->create($data);
        
        return $this->success(['id' => $permission->id], '创建成功');
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        $permission = $this->permissionService->getById($id);
        $fields = $this->permissionService->getFormFields('update', $permission);
        $formSchema = [
            'title' => '编辑权限',
            'fields' => $fields,
            'submitUrl' => admin_route("system/permissions/{$id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/permissions'),
        ];
        
        return $this->renderAdmin('admin.system.permission.edit', [
            'permission' => $permission,
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 更新数据
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        $data = $request->all();
        $this->validateData($data, 'update', $id);
        
        $permission = $this->permissionService->update($id, $data);
        
        return $this->success(['id' => $permission->id], '更新成功');
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->permissionService->delete($id);
        return $this->success(null, '删除成功');
    }

    /**
     * 批量删除
     */
    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return $this->error('请选择要删除的记录');
        }
        
        $count = $this->permissionService->batchDelete($ids);
        return $this->success(null, "成功删除 {$count} 条记录");
    }
}

