<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminRole;
use App\Service\Admin\RoleService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RoleController extends BaseModelCrudController
{
    #[Inject]
    protected RoleService $roleService;

    protected function getModelClass(): string
    {
        return AdminRole::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [
            'create' => [
                'name' => 'required|string|max:50',
                'slug' => 'required|string|max:50',
                'permission_ids' => 'array',
            ],
            'update' => [
                'name' => 'required|string|max:50',
                'slug' => 'required|string|max:50',
                'permission_ids' => 'array',
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

        return $this->renderAdmin('admin.system.role.index');
    }

    /**
     * 获取列表数据
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $result = $this->roleService->getList($params);
        
        // 统一返回格式：将 list 改为 data，并添加 last_page
        return $this->success([
            'data' => $result['list'] ?? [],
            'total' => $result['total'] ?? 0,
            'page' => $result['page'] ?? 1,
            'page_size' => $result['page_size'] ?? 15,
            'last_page' => isset($result['page_size']) && $result['page_size'] > 0 
                ? (int) ceil(($result['total'] ?? 0) / $result['page_size']) 
                : 1,
        ]);
    }

    /**
     * 创建页面
     */
    public function create(RequestInterface $request): ResponseInterface
    {
        $fields = $this->roleService->getFormFields('create');
        $formSchema = [
            'title' => '新增角色',
            'fields' => $fields,
            'submitUrl' => admin_route('system/roles'),
            'method' => 'POST',
            'redirectUrl' => admin_route('system/roles'),
        ];

        return $this->renderAdmin('admin.system.role.create', [
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 保存数据
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        $data = $request->all();
        if (! array_key_exists('permission_ids', $data)) {
            $data['permission_ids'] = [];
        }
        $this->validateData($data, 'create');
        $role = $this->roleService->create($data);

        return $this->success(['id' => $role->id], '创建成功');
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        $role = $this->roleService->getById($id);
        $fields = $this->roleService->getFormFields('update', $role);
        $formSchema = [
            'title' => '编辑角色',
            'fields' => $fields,
            'submitUrl' => admin_route("system/roles/{$id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/roles'),
        ];
        
        return $this->renderAdmin('admin.system.role.edit', [
            'role' => $role,
            'formSchemaJson' => json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * 更新数据
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        $data = $request->all();
        if (! array_key_exists('permission_ids', $data)) {
            $data['permission_ids'] = [];
        }
        $this->validateData($data, 'update', $id);
        $role = $this->roleService->update($id, $data);
        
        return $this->success(['id' => $role->id], '更新成功');
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->roleService->delete($id);
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
        
        $count = $this->roleService->batchDelete($ids);
        return $this->success(null, "成功删除 {$count} 条记录");
    }
}

