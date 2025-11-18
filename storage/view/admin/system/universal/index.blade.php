@extends('admin.layouts.admin')

@section('title', ($config['title'] ?? '数据') . '列表')

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

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
    // 优先使用标准的 features 数组，其次兼容旧的 feature_* 字段
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
    } else {
        // 兼容旧字段：feature_search / feature_add / feature_edit / feature_delete / feature_export
        $legacy = [
            'search' => $config['feature_search'] ?? null,
            'add' => $config['feature_add'] ?? null,
            'edit' => $config['feature_edit'] ?? null,
            'delete' => $config['feature_delete'] ?? null,
            'export' => $config['feature_export'] ?? null,
        ];
        foreach ($legacy as $key => $value) {
            if ($value !== null) {
                $featureDefaults[$key] = (bool) $value;
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
@endphp

{{-- 配置代码查看器文件路径和删除路由模板 --}}
@push('scripts')
<script>
window.CODE_VIEWER_FILES = @json($codeViewerFiles);
// 设置删除路由模板（供组件使用，不需要等待 DOM 就绪）
window.destroyRouteTemplate_dataTable = '{{ $destroyRouteTemplate }}';
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
            {{-- 添加操作列（在视图文件中定义，便于自定义） --}}
            @php
                // 计算操作列的 index（基于最后一个列的 index + 1）
                $actionColumnIndex = 0;
                if (!empty($columns)) {
                    // 获取最后一个列的 index
                    $lastColumn = end($columns);
                    $actionColumnIndex = ($lastColumn['index'] ?? count($columns) - 1) + 1;
                }
                
                // 构建操作列配置
                $actionColumn = [
                    'index' => $actionColumnIndex,
                    'label' => '操作',
                    'type' => 'actions',
                    'visible' => true,
                    'width' => '120',
                    'class' => 'sticky-column',
                    'toggleable' => false,
                    'sortable' => false,  // 操作列不支持排序
                ];
                
                // 根据功能开关构建操作按钮
                $actionColumnActions = [];
                // 编辑按钮：受 feature_edit 和 readonly 控制
                if (empty($config['readonly']) && ($features['edit'] ?? true)) {
                    $actionColumnActions[] = [
                        'type' => 'link',
                        'href' => $editRouteTemplate . '/{id}/edit',
                        'icon' => 'bi-pencil',
                        'variant' => 'warning',
                        'title' => '编辑',
                        'visible' => true,
                    ];
                }
                // 删除按钮：受 feature_delete 控制
                if (empty($config['readonly']) && ($features['delete'] ?? true)) {
                    $actionColumnActions[] = [
                        'type' => 'button',
                        'onclick' => 'deleteRow_dataTable({id})',
                        'icon' => 'bi-trash',
                        'variant' => 'danger',
                        'title' => '删除',
                        'visible' => true,
                    ];
                }
                
                if (!empty($actionColumnActions)) {
                    $actionColumn['actions'] = $actionColumnActions;
                } else {
                    // 如果既不能编辑也不能删除，则隐藏操作列
                    $actionColumn['visible'] = false;
                }
                
                // 将操作列添加到列数组中
                $columns[] = $actionColumn;
            @endphp

            {{-- 使用集成了工具栏和搜索表单的数据表格组件（AJAX 模式） --}}
            <div id="dataTableContainer">
                @include('admin.components.data-table-with-columns', [
                    'tableId' => 'dataTable',
                    'storageKey' => $storageKey,
                    'columns' => $columns,
                    'data' => $data,  // 初始数据（AJAX 模式下会被替换）
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
                    'exportRoute' => $exportRoute,  // 传递导出路由，组件会自动生成导出函数
                    'showPagination' => true,
                    'leftButtons' => array_filter(array_merge(empty($config['readonly']) ? [
                        // 新增按钮：受 feature_add 控制
                        ($features['add'] ?? true) ? [
                            'type' => 'link',
                            'href' => $createRoute,
                            'text' => '添加',
                            'icon' => 'bi-plus-lg',
                            'variant' => 'primary'
                        ] : null,
                        // 批量删除按钮：需要有路由且受 feature_delete 控制
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
                        // 导出按钮：受 feature_export 控制
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
                    // 删除确认模态框配置（可选，使用默认值）
                    // 'deleteModalId' => 'deleteModal_dataTable',  // 默认值，可自定义
                    // 'deleteConfirmMessage' => '确定要删除这条记录吗？',  // 默认值，可自定义
                    // 'deleteWarningMessage' => '警告：删除后将无法恢复！',  // 默认值，可自定义
                    // 'deleteModalTitle' => '确认删除',  // 默认值，可自定义
                    // 'deleteConfirmButtonText' => '确认删除',  // 默认值，可自定义
                    // 'deleteCancelButtonText' => '取消',  // 默认值，可自定义
                    // 批量删除确认模态框配置（可选，使用默认值）
                    // 'batchDeleteModalId' => 'batchDeleteModal_dataTable',  // 默认值：batchDeleteModal_{tableId}，设置为 false 则不显示模态框
                    // 'batchDeleteConfirmMessage' => '确定要删除选中的 {count} 条记录吗？',  // 默认值，支持 {count} 占位符
                    // 'batchDeleteWarningMessage' => '警告：删除后将无法恢复！',  // 默认值，可自定义
                    // 'batchDeleteModalTitle' => '确认批量删除',  // 默认值，可自定义
                    // 'batchDeleteConfirmButtonText' => '确认删除',  // 默认值，可自定义
                    // 'batchDeleteCancelButtonText' => '取消',  // 默认值，可自定义

                    
                ])
            </div>
        </div>
    </div>
</div>

@include('admin.common.scripts')


@endsection
