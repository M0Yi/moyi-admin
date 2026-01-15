<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\Admin\BaseModelCrudController;
use App\Model\Admin\AdminErrorStatistic;
use App\Service\Admin\ErrorStatisticService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ErrorStatisticController extends BaseModelCrudController
{
    #[Inject]
    protected ErrorStatisticService $errorStatisticService;

    protected function getModelClass(): string
    {
        return AdminErrorStatistic::class;
    }

    protected function getValidationRules(string $scene, ?int $id = null): array
    {
        // 错误统计只读，不需要验证规则
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

        $searchFields = ['username', 'exception_class', 'error_message', 'error_level', 'request_path', 'request_ip', 'status'];
        $fields = [
            [
                'name' => 'username',
                'label' => '用户名',
                'type' => 'text',
                'placeholder' => '请输入用户名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'exception_class',
                'label' => '异常类',
                'type' => 'text',
                'placeholder' => '请输入异常类名',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'error_message',
                'label' => '错误消息',
                'type' => 'text',
                'placeholder' => '请输入错误消息关键词',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'request_path',
                'label' => '请求路径',
                'type' => 'text',
                'placeholder' => '请输入请求路径',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'request_ip',
                'label' => 'IP地址',
                'type' => 'text',
                'placeholder' => '请输入IP地址',
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'error_level',
                'label' => '错误等级',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '全部'],
                    ['value' => 'error', 'label' => '错误'],
                    ['value' => 'warning', 'label' => '警告'],
                    ['value' => 'notice', 'label' => '通知'],
                ],
                'col' => 'col-12 col-md-3',
            ],
            [
                'name' => 'status',
                'label' => '状态',
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => '全部'],
                    ['value' => '0', 'label' => '未处理'],
                    ['value' => '1', 'label' => '处理中'],
                    ['value' => '2', 'label' => '已解决'],
                ],
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
                'options' => $this->errorStatisticService->getSiteFilterOptions(),
                'placeholder' => '请选择站点',
                'col' => 'col-12 col-md-3',
            ];
        }

        $searchConfig = [
            'search_fields' => $searchFields,
            'fields' => $fields,
            'status_filter' => [
                'enabled' => true,
                'title' => '状态筛选',
                'show_all' => true,
                'options' => [
                    ['value' => '0', 'label' => '未处理', 'color' => 'danger'],
                    ['value' => '1', 'label' => '处理中', 'color' => 'warning'],
                    ['value' => '2', 'label' => '已解决', 'color' => 'success'],
                ],
            ],
        ];

        return $this->renderAdmin('admin.system.error-statistic.index', [
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

        $result = $this->errorStatisticService->getList($params);

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
        $errorStat = $this->errorStatisticService->getById($id);

        return $this->renderAdmin('admin.system.error-statistic.show', [
            'errorStat' => $errorStat,
        ]);
    }

    /**
     * 删除数据
     */
    public function destroy(RequestInterface $request, int $id): ResponseInterface
    {
        $this->errorStatisticService->delete($id);
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

    /**
     * 标记为已解决
     */
    public function resolve(RequestInterface $request, int $id): ResponseInterface
    {
        $this->errorStatisticService->resolve($id);
        return $this->success(null, '标记为已解决成功');
    }

    /**
     * 批量标记为已解决
     */
    public function batchResolve(RequestInterface $request): ResponseInterface
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return $this->error('请选择要标记的记录');
        }

        $count = $this->errorStatisticService->batchResolve($ids);
        return $this->success(['count' => $count], "成功标记 {$count} 条记录为已解决");
    }
}
