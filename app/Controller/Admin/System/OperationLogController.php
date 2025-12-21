<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminOperationLog;
use App\Service\Admin\OperationLogService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OperationLogController extends BaseModelCrudController
{
    #[Inject]
    protected OperationLogService $operationLogService;

    protected function getModelClass(): string
    {
        return AdminOperationLog::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        // 操作日志只读，不需要验证规则
        return [];
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

        $searchFields = ['username', 'method', 'path', 'ip'];
        $fields = [
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'placeholder' => '请输入用户名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'method',
                'label' => '请求方法',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '全部'],
                    ['value' => 'GET', 'label' => 'GET'],
                    ['value' => 'POST', 'label' => 'POST'],
                    ['value' => 'PUT', 'label' => 'PUT'],
                    ['value' => 'DELETE', 'label' => 'DELETE'],
                    ['value' => 'PATCH', 'label' => 'PATCH'],
                ],
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'path',
                'label' => '请求路径',
                'type' => 'text',
                'placeholder' => '请输入请求路径',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'ip',
                'label' => 'IP地址',
                'type' => 'text',
                'placeholder' => '请输入IP地址',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'start_date',
                'label' => '开始日期',
                'type' => 'date',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'end_date',
                'label' => '结束日期',
                'type' => 'date',
                'col' => 'col-12 col-md-3',
            ],
        ];

        if (is_super_admin()) {
            $searchFields[] = 'site_id';
            $fields[] = [
                'name' => 'site_id',
                'label' => '所属站点',
                'type' => 'select',
                'options' => $this->operationLogService->getSiteFilterOptions(),
                'placeholder' => '请选择站点',
                'col' => 'col-12 col-md-3',
            ];
        }

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
        ];

        return $this->renderAdmin('admin.system.operation-log.index', [
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

        $result = $this->operationLogService->getList($params);
        
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
     * 详情页面
     */
    public function show(RequestInterface $request, int $id): ResponseInterface
    {
        $log = $this->operationLogService->getById($id);
        
        return $this->renderAdmin('admin.system.operation-log.show', [
            'log' => $log,
        ]);
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->operationLogService->delete($id);
        return $this->success(null, '删除成功');
    }

    /**
     * 批量删除
     */
    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        // 批量删除功能已禁用
        return $this->error('批量删除功能已被禁用', null, 403);
    }
}

