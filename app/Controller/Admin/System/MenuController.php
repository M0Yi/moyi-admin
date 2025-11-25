<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Exception\BusinessException;
use App\Exception\ValidationException;
use App\Model\Admin\AdminMenu;
use App\Service\Admin\MenuService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 菜单管理控制器
 * 
 * 基于 BaseModelCrudController 重构，减少重复代码
 */
class MenuController extends BaseModelCrudController
{
    #[Inject]
    protected MenuService $menuService;

    /**
     * 指定 Model 类
     */
    protected function getModelClass(): string
    {
        return AdminMenu::class;
    }

    /**
     * 获取验证规则
     */
    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [
            'create' => [
                'name' => 'required|string|max:100',
                'title' => 'required|string|max:100',
                'parent_id' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:50',
                'path' => 'nullable|string|max:255',
                'component' => 'nullable|string|max:255',
                'redirect' => 'nullable|string|max:255',
                'type' => 'nullable|in:menu,link,group,divider',
                'target' => 'nullable|in:_self,_blank',
                'badge' => 'nullable|string|max:50',
                'badge_type' => 'nullable|in:primary,success,warning,danger,info',
                'permission' => 'nullable|string|max:100',
                'visible' => 'nullable|in:0,1',
                'status' => 'nullable|in:0,1',
                'sort' => 'nullable|integer|min:0',
                'cache' => 'nullable|in:0,1',
                'remark' => 'nullable|string',
            ],
            'update' => [
                'name' => 'required|string|max:100',
                'title' => 'required|string|max:100',
                'parent_id' => 'nullable|integer|min:0',
                'icon' => 'nullable|string|max:50',
                'path' => 'nullable|string|max:255',
                'component' => 'nullable|string|max:255',
                'redirect' => 'nullable|string|max:255',
                'type' => 'nullable|in:menu,link,group,divider',
                'target' => 'nullable|in:_self,_blank',
                'badge' => 'nullable|string|max:50',
                'badge_type' => 'nullable|in:primary,success,warning,danger,info',
                'permission' => 'nullable|string|max:100',
                'visible' => 'nullable|in:0,1',
                'status' => 'nullable|in:0,1',
                'sort' => 'nullable|integer|min:0',
                'cache' => 'nullable|in:0,1',
                'remark' => 'nullable|string',
            ],
        ][$scene] ?? [];
    }

    /**
     * 获取列表查询构建器
     * 菜单需要按 sort 和 id 排序
     */
    protected function getListQuery()
    {
        $query = parent::getListQuery();
        $query->orderBy('sort', 'asc')->orderBy('id', 'asc');
        return $query;
    }

    /**
     * 获取可搜索字段
     */
    protected function getSearchableFields(): array
    {
        return ['id', 'name', 'title', 'path'];
    }

    /**
     * 获取可排序字段
     */
    protected function getSortableFields(): array
    {
        return ['id', 'sort', 'name', 'title', 'status', 'created_at', 'updated_at'];
    }

    /**
     * 获取默认排序字段
     */
    protected function getDefaultSortField(): string
    {
        return 'sort';
    }

    /**
     * 获取默认排序方向
     */
    protected function getDefaultSortOrder(): string
    {
        return 'asc';
    }

    /**
     * 获取字段标签映射
     */
    protected function getFieldLabels(): array
    {
        return array_merge(parent::getFieldLabels(), [
            'name' => '菜单名称',
            'title' => '菜单标题',
            'parent_id' => '父级菜单',
            'type' => '菜单类型',
            'path' => '路由路径',
        ]);
    }

    /**
     * 渲染列表页面
     */
    protected function renderListPage(RequestInterface $request): ResponseInterface
    {
        return $this->renderAdmin('admin.system.menu.index', [
            'diagnostics' => $this->buildDiagnostics(),
        ]);
    }

    /**
     * 获取菜单列表数据（API）
     * 菜单需要扁平化列表（带层级缩进），所以重写此方法
     */
    public function listData(RequestInterface $request): ResponseInterface
    {
        try {
            $params = [
                'site_id' => site_id() ?? 0,
            ];

            // 获取扁平化列表数据
            $list = $this->menuService->getList($params);

            // 分页处理
            $page = (int) $request->input('page', 1);
            $pageSize = (int) $request->input('page_size', 20);

            if ($pageSize > 0) {
                $total = count($list);
                $offset = ($page - 1) * $pageSize;
                $list = array_slice($list, $offset, $pageSize);

                return $this->success([
                    'data' => $list,
                    'total' => $total,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'last_page' => (int) ceil($total / $pageSize),
                ]);
            }

            return $this->success([
                'data' => $list,
                'total' => count($list),
                'page' => 1,
                'page_size' => count($list),
                'last_page' => 1,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 渲染创建页面
     */
    protected function renderCreatePage(RequestInterface $request): ResponseInterface
    {
        // 获取表单字段配置
        $fields = $this->menuService->getFormFields('create');
        
        // 构建表单 schema
        $formSchema = [
            'title' => '新增菜单',
            'fields' => $fields,
            'submitUrl' => admin_route('system/menus'),
            'method' => 'POST',
            'redirectUrl' => admin_route('system/menus'),
            'endpoints' => [
                'uploadToken' => admin_route('api/admin/upload/token'),
            ],
        ];

        $formSchemaJson = json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->renderAdmin('admin.system.menu.create', [
            'formSchemaJson' => $formSchemaJson,
            'diagnostics' => $this->buildDiagnostics(),
        ]);
    }

    /**
     * 渲染编辑页面
     */
    protected function renderEditPage(RequestInterface $request, \App\Model\Model $model): ResponseInterface
    {
        $menu = $this->menuService->getById($model->id);
        
        // 获取表单字段配置（传入菜单对象以填充默认值）
        $fields = $this->menuService->getFormFields('edit', $menu);
        
        // 构建表单 schema
        $formSchema = [
            'title' => '编辑菜单',
            'fields' => $fields,
            'submitUrl' => admin_route("system/menus/{$model->id}"),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/menus'),
            'endpoints' => [
                'uploadToken' => admin_route('api/admin/upload/token'),
            ],
        ];

        $formSchemaJson = json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->renderAdmin('admin.system.menu.edit', [
            'menu' => $menu,
            'formSchemaJson' => $formSchemaJson,
            'diagnostics' => $this->buildDiagnostics(),
        ]);
    }

    /**
     * 保存数据
     * 处理 linkPath 字段映射到 path，并使用 Service 进行业务逻辑验证
     */
    public function store(RequestInterface $request): ResponseInterface
    {
        try {
            $data = $request->all();

            // 处理 linkPath 字段（外链类型时，linkPath 映射到 path）
            if (isset($data['linkPath']) && !empty($data['linkPath'])) {
                $data['path'] = $data['linkPath'];
                unset($data['linkPath']);
            }

            // 数据验证（使用基类的验证方法）
            $this->validateData($data, 'create');

            // 使用 Service 创建（包含业务逻辑验证，如父级菜单检查、路径唯一性等）
            $menu = $this->menuService->create($data);

            return $this->success(['id' => $menu->id], '创建成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 更新数据
     * 处理 linkPath 字段映射到 path，并使用 Service 进行业务逻辑验证
     */
    public function update(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $data = $request->all();

            // 处理 linkPath 字段（外链类型时，linkPath 映射到 path）
            if (isset($data['linkPath']) && !empty($data['linkPath'])) {
                $data['path'] = $data['linkPath'];
                unset($data['linkPath']);
            }

            // 数据验证（使用基类的验证方法）
            $this->validateData($data, 'update', $id);

            // 使用 Service 更新（包含业务逻辑验证，如父级菜单检查、路径唯一性等）
            $this->menuService->update($id, $data);

            return $this->success([], '更新成功');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), [
                'errors' => $e->getErrors(),
            ], 422);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 删除数据
     * 需要检查是否有子菜单
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            $this->menuService->delete($id);
            return $this->success([], '删除成功');
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 批量删除
     * 需要检查是否有子菜单
     */
    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        try {
            $ids = $request->input('ids', []);

            if (empty($ids) || !is_array($ids)) {
                return $this->error('请选择要删除的菜单');
            }

            $count = $this->menuService->batchDelete($ids);

            return $this->success(['count' => $count], "成功删除 {$count} 个菜单");
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 更新菜单排序
     */
    public function updateSort(RequestInterface $request): ResponseInterface
    {
        try {
            $sorts = $request->input('sorts', []);

            if (empty($sorts) || !is_array($sorts)) {
                return $this->error('排序数据不能为空');
            }

            $this->menuService->updateSort($sorts);

            return $this->success([], '排序更新成功');
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * 构建诊断信息
     */
    private function buildDiagnostics(): array
    {
        $queryParams = $this->request->getQueryParams();
        $serverParams = $this->request->getServerParams();

        return [
            'query' => $queryParams,
            'channel' => $queryParams['_channel'] ?? null,
            'sec_fetch_dest' => $serverParams['HTTP_SEC_FETCH_DEST'] ?? null,
        ];
    }
}

