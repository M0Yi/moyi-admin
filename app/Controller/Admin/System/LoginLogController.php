<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminLoginLog;
use App\Service\Admin\LoginLogService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class LoginLogController extends BaseModelCrudController
{
    #[Inject]
    protected LoginLogService $loginLogService;

    protected function getModelClass(): string
    {
        return AdminLoginLog::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        return [];
    }

    public function index(RequestInterface $request): ResponseInterface
    {
        if ($request->input('_ajax') === '1' || $request->input('format') === 'json') {
            return $this->listData($request);
        }

        $searchFields = ['username', 'ip', 'status'];
        $fields = [
            ['name' => 'username', 'label' => '用户名', 'type' => 'text', 'col' => 'col-12 col-md-3'],
            ['name' => 'ip', 'label' => 'IP', 'type' => 'text', 'col' => 'col-12 col-md-3'],
            ['name' => 'status', 'label' => '状态', 'type' => 'select', 'options' => [
                ['value' => '', 'label' => '全部'],
                ['value' => '1', 'label' => '成功'],
                ['value' => '0', 'label' => '失败'],
            ], 'col' => 'col-12 col-md-3'],
            ['name' => 'start_date', 'label' => '开始日期', 'type' => 'date', 'col' => 'col-12 col-md-3'],
            ['name' => 'end_date', 'label' => '结束日期', 'type' => 'date', 'col' => 'col-12 col-md-3'],
        ];

        $searchConfig = ['search_fields' => $searchFields, 'fields' => $fields];

        return $this->renderAdmin('admin.system.login-log.index', [
            'searchConfig' => $searchConfig,
        ]);
    }

    public function listData(RequestInterface $request): ResponseInterface
    {
        $params = $request->all();
        $filters = $this->normalizeFilters($request->input('filters', []));
        if (!empty($filters)) {
            $params = array_merge($params, $filters);
        }
        unset($params['filters']);

        $result = $this->loginLogService->getList($params);

        return $this->success([
            'data' => $result['list'] ?? [],
            'total' => $result['total'] ?? 0,
            'page' => $result['page'] ?? 1,
            'page_size' => $result['page_size'] ?? 15,
            'last_page' => isset($result['page_size']) && $result['page_size'] > 0 ? (int) ceil(($result['total'] ?? 0) / $result['page_size']) : 1,
        ]);
    }

    public function show(RequestInterface $request, int $id): ResponseInterface
    {
        $log = $this->loginLogService->getById($id);
        return $this->renderAdmin('admin.system.login-log.show', ['log' => $log]);
    }

    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->loginLogService->delete($id);
        return $this->success(null, '删除成功');
    }

    public function batchDestroy(RequestInterface $request): ResponseInterface
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return $this->error('请选择要删除的记录');
        }
        $count = $this->loginLogService->batchDelete($ids);
        return $this->success(null, "成功删除 {$count} 条记录");
    }
}


