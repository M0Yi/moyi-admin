<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminUser;
use App\Service\Admin\UserService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class UserController extends BaseModelCrudController
{
    #[Inject]
    protected UserService $userService;

    protected function getModelClass(): string
    {
        return AdminUser::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        // 验证规则已在 Service 层或 Model 层处理，这里保留基本结构
        // 实际验证逻辑在 UserService 中通过 create/update 方法内部校验（如唯一性）
        // 如果需要 Request 验证，可以在这里定义
        return [
            'create' => [
                'username' => 'required|string|max:50',
                'password' => 'required|string|min:6',
                'role_ids' => 'array',
            ],
            'update' => [
                'username' => 'required|string|max:50',
                'role_ids' => 'array',
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

        return $this->renderAdmin('admin.system.user.index');
    }

    /**
     * 获取列表数据
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $result = $this->userService->getList($params);
        
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
        $fields = $this->userService->getFormFields('create');
        $formSchema = [
            'title' => '新增用户',
            'fields' => $fields,
            'submitUrl' => admin_route('system/users'),
            'method' => 'POST',
            'redirectUrl' => admin_route('system/users'),
        ];

        return $this->renderAdmin('admin.system.user.create', [
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
        
        $user = $this->userService->create($data);
        
        return $this->success(['id' => $user->id], '创建成功');
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        $user = $this->userService->getById($id);
        $fields = $this->userService->getFormFields('update', $user);
        $formSchema = [
            'title' => '编辑用户',
            'fields' => $fields,
            'submitUrl' => admin_route("system/users/{$id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/users'),
        ];
        
        return $this->renderAdmin('admin.system.user.edit', [
            'user' => $user,
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
        
        $user = $this->userService->update($id, $data);
        
        return $this->success(['id' => $user->id], '更新成功');
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->userService->delete($id);
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
        
        $count = $this->userService->batchDelete($ids);
        return $this->success(null, "成功删除 {$count} 条记录");
    }
}

