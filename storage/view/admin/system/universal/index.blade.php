@extends('admin.layouts.admin')

@section('title', ($config['title'] ?? '数据') . '列表')

@if (! ($isEmbedded ?? false))
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
{{-- CRUD 路由定义 --}}
@php
    // 基础路由前缀
    $baseRoute = "u/{$model}";
    
    // CRUD 路由
    $indexRoute = admin_route($baseRoute);                    // 列表页/API
    $createRoute = admin_route("{$baseRoute}/create");        // 创建页
    $editRouteTemplate = admin_route($baseRoute);             // 编辑页模板（需要拼接 /{id}/edit）
    $batchDestroyRoute = admin_route("{$baseRoute}/batch-destroy");  // 批量删除
    $destroyRouteTemplate = admin_route($baseRoute);         // 删除模板（需要拼接 /{id}）
    $exportRoute = admin_route("{$baseRoute}/export");         // 导出路由
    
    // 本地存储键名（用于保存列显示设置）
    $storageKey = 'universal_' . $model . '_columns';
    
    // 功能开关配置：增 / 删 / 改 / 查 / 导出 等功能是否启用
    $featureDefaults = [
        'search' => true,
        'add' => true,
        'edit' => true,
        'delete' => true,
        'export' => true,
    ];

    if (!empty($config['features']) && is_array($config['features'])) {
        foreach ($featureDefaults as $key => $default) {
            if (array_key_exists($key, $config['features'])) {
                $featureDefaults[$key] = (bool) $config['features'][$key];
            }
        }
    }

    $features = $featureDefaults;
    
    // 代码查看器文件路径
    $codeViewerFiles = [
        'controller' => 'app/Controller/Admin/System/UniversalCrudController.php',
        'view' => 'storage/view/admin/system/universal/index.blade.php'
    ];
    if (isset($config['model_class'])) {
        $codeViewerFiles['model'] = str_replace('\\', '/', str_replace('App\\', 'app/', $config['model_class'])) . '.php';
    }

    $shellChannel = 'universal-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($model));
@endphp

{{-- 配置代码查看器文件路径和删除路由模板 --}}
@push('admin_scripts')
<script type="application/json" id="universalCrudConfigPayload">
{!! $configJson ?? '{}' !!}
</script>
<script>
(function () {
    window.CODE_VIEWER_FILES = @json($codeViewerFiles);
    // 设置删除路由模板（供组件使用，不需要等待 DOM 就绪）
    window.destroyRouteTemplate_dataTable = '{{ $destroyRouteTemplate }}';

    try {
        const payloadElement = document.getElementById('universalCrudConfigPayload');
        const payloadText = payloadElement ? (payloadElement.textContent || '{}') : '{}';
        window.universalCrudConfig = payloadText ? JSON.parse(payloadText) : {};
    } catch (error) {
        console.error('[UniversalCrud] 解析配置失败', error);
        window.universalCrudConfig = {};
    }

    document.addEventListener('click', function (event) {
        const editButton = event.target.closest('#dataTable a.btn-warning.btn-action');
        if (!editButton) {
            return;
        }

        const targetUrl = editButton.getAttribute('href');
        if (!targetUrl || !window.Admin || !window.Admin.iframeShell) {
            return;
        }

        event.preventDefault();
        window.Admin.iframeShell.open({
            src: targetUrl,
            title: '{{ ($config['title'] ?? '数据') }}编辑',
            channel: '{{ $shellChannel }}'
        });
    }, true);
})();
</script>
@endpush

<div class="container-fluid py-4">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">{{ $config['title'] ?? '数据' }}列表</h6>
        <small class="text-muted">{{ $config['description'] ?? '管理' . ($config['title'] ?? '数据') . '信息' }}</small>
    </div>

    <!-- 数据列表卡片 -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            {{-- 使用集成了工具栏和搜索表单的数据表格组件（AJAX 模式） --}}
            <div id="dataTableContainer">
                @include('admin.components.data-table-with-columns', [
                    'tableId' => 'dataTable',
                    'storageKey' => $storageKey,
                    'emptyMessage' => '加载中...',
                    'ajaxUrl' => $indexRoute,  // 启用 AJAX 模式
                    'searchFormId' => 'searchForm_dataTable',
                    'searchPanelId' => 'searchPanel_dataTable',
                    'searchConfig' => $config,  // 传递搜索配置，组件会自动渲染搜索表单
                    'model' => $model,  // 传递模型名称，用于关联模式字段的异步加载
                    'batchDestroyRoute' => $batchDestroyRoute,  // 批量删除路由
                    'defaultPageSize' => $config['default_page_size'] ?? 15,  // 默认分页尺寸
                    'pageSizeOptions' => $config['page_size_options'] ?? [10, 15, 20, 50, 100],  // 可选的每页数量选项
                    'enablePageSizeStorage' => $config['enable_page_size_storage'] ?? true,  // 是否保存分页尺寸到 localStorage
                    'defaultSortField' => $config['default_sort_field'] ?? 'id',
                    'defaultSortOrder' => $config['default_sort_order'] ?? 'desc',
                    'editRouteTemplate' => $editRouteTemplate,
                    'actionColumnConfig' => $config['action_column'] ?? [],
                    'exportRoute' => $exportRoute,  // 传递导出路由，组件会自动生成导出函数
                    'showPagination' => true,
                    'leftButtons' => array_filter(array_merge(empty($config['readonly']) ? [
                        // 新增按钮：受 add 功能开关控制
                        ($features['add'] ?? true) ? [
                            'type' => 'link',
                            'href' => $createRoute,
                            'text' => '添加',
                            'icon' => 'bi-plus-lg',
                            'variant' => 'primary',
                            'attributes' => [
                                'data-iframe-shell-trigger' => 'universal-create-' . $model,
                                'data-iframe-shell-src' => $createRoute,
                                'data-iframe-shell-title' => '新建' . ($config['title'] ?? '数据'),
                                'data-iframe-shell-channel' => $shellChannel,
                                'data-iframe-shell-auto-close' => 'true',
                            ],
                        ] : null,
                        // 批量删除按钮：需要有路由且受 delete 功能开关控制
                        (!empty($batchDestroyRoute) && ($features['delete'] ?? true)) ? [
                            'type' => 'button',
                            'text' => '批量删除',
                            'icon' => 'bi-trash',
                            'variant' => 'danger',
                            'id' => 'batchDeleteBtn_dataTable',
                            'onclick' => 'batchDelete_dataTable()',
                            'title' => '批量删除选中的记录'
                        ] : null
                    ] : []), function($item) {
                        return $item !== null;
                    }),
                    'rightButtons' => array_filter([
                        // 导出按钮：受 export 功能开关控制
                        ($features['export'] ?? true) ? [
                            'icon' => 'bi-download',
                            'title' => '导出',
                            'onclick' => 'exportData_dataTable()'
                        ] : null,
                        // 刷新按钮始终保留
                        [
                            'icon' => 'bi-arrow-repeat',
                            'title' => '刷新',
                            'onclick' => 'loadData_dataTable()'
                        ],
                    ], function ($item) {
                        return $item !== null;
                    }),
                    // 搜索：需要有搜索字段且启用了 search 功能开关
                    'showSearch' => !empty($config['search_fields']) && ($features['search'] ?? true),
                ])
            </div>
        </div>
    </div>
</div>

@endsection
