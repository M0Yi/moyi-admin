<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminDatabaseConnection;
use App\Service\Admin\DatabaseConnectionService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 远程数据库连接管理控制器
 */
class DatabaseConnectionController extends BaseModelCrudController
{
    #[Inject]
    protected DatabaseConnectionService $service;

    protected function getModelClass(): string
    {
        return AdminDatabaseConnection::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [
            'create' => [
                'name' => 'required|string|max:50',
                'driver' => 'required|string|in:mysql,pgsql',
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
                'database' => 'required|string|max:100',
                'username' => 'required|string|max:100',
                'password' => 'required|string|max:255',
                'charset' => 'nullable|string|max:20',
                'collation' => 'nullable|string|max:50',
                'prefix' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:255',
                'status' => 'required|integer|in:0,1',
                'sort' => 'nullable|integer',
            ],
            'update' => [
                'name' => 'required|string|max:50',
                'driver' => 'required|string|in:mysql,pgsql',
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
                'database' => 'required|string|max:100',
                'username' => 'required|string|max:100',
                'password' => 'nullable|string|max:255',
                'charset' => 'nullable|string|max:20',
                'collation' => 'nullable|string|max:50',
                'prefix' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:255',
                'status' => 'required|integer|in:0,1',
                'sort' => 'nullable|integer',
            ],
        ][$scene] ?? [];
    }

    /**
     * 列表页面
     */
    public function index(RequestInterface $request): ResponseInterface
    {
        // 如果是 AJAX 请求，返回 JSON 数据
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $searchFields = ['name', 'host', 'database', 'description'];
        $fields = [
            [
                'name' => 'name',
                'label' => '连接名称',
                'type' => 'text',
                'placeholder' => '请输入连接名称',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'host',
                'label' => '主机地址',
                'type' => 'text',
                'placeholder' => '请输入主机地址',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'database',
                'label' => '数据库名',
                'type' => 'text',
                'placeholder' => '请输入数据库名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '全部'],
                    ['value' => '1', 'label' => '启用'],
                    ['value' => '0', 'label' => '禁用'],
                ],
                'placeholder' => '请选择状态',
                'col' => 'col-12 col-md-3',
            ],
        ];

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->renderAdmin('admin.system.database-connection.index', [
            'searchConfig' => $searchConfig,
        ]);
    }

    /**
     * 获取列表数据
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $filters = $this->normalizeFilters($request->input('filters', []));
        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        unset($params['filters']);

        $result = $this->service->getList($params);

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
        $fields = $this->service->getFormFields('create');
        $formSchema = [
            'title' => '新增数据库连接',
            'fields' => $fields,
            'submitUrl' => admin_route('system/database-connections'),
            'method' => 'POST',
            'redirectUrl' => admin_route('system/database-connections'),
        ];

        return $this->renderAdmin('admin.system.database-connection.create', [
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

        $connection = $this->service->create($data);

        return $this->success(['id' => $connection->id], '创建成功');
    }

    /**
     * 编辑页面
     */
    public function edit(RequestInterface $request, int $id): ResponseInterface
    {
        $connection = $this->service->getById($id);
        $fields = $this->service->getFormFields('update', $connection);
        $formSchema = [
            'title' => '编辑数据库连接',
            'fields' => $fields,
            'submitUrl' => admin_route("system/database-connections/{$id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/database-connections'),
        ];

        return $this->renderAdmin('admin.system.database-connection.edit', [
            'connection' => $connection,
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

        $connection = $this->service->update($id, $data);

        return $this->success(['id' => $connection->id], '更新成功');
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->service->delete($id);
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

        $count = $this->service->batchDelete($ids);
        return $this->success(null, "成功删除 {$count} 条记录");
    }

    /**
     * 测试数据库连接
     */
    public function testConnection(RequestInterface $request, int $id): ResponseInterface
    {
        // 如果提供了密码，使用提供的密码；否则使用存储的密码
        $password = $request->input('password');
        $result = $this->service->testConnection($id, $password ?: null);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message']);
    }
}

