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
    $trashRoute = admin_route("{$baseRoute}/trash");           // 回收站路由
    
    // 本地存储键名（用于保存列显示设置）
    $storageKey = 'universal_' . $model . '_columns';
    
    // 功能开关配置：增 / 删 / 改 / 查 / 导出 / 软删除 等功能是否启用
    $featureDefaults = [
        'search' => true,
        'add' => true,
        'edit' => true,
        'delete' => true,
        'export' => true,
        'soft_delete' => false,  // 软删除默认关闭
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
@include('components.admin-script', ['path' => '/js/components/search-form-renderer.js'])
{{-- 引入通用刷新父页面监听器 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
(function () {
    window.CODE_VIEWER_FILES = @json($codeViewerFiles);
    // 设置删除路由模板（供组件使用，不需要等待 DOM 就绪）
    window.destroyRouteTemplate_dataTable = '{{ $destroyRouteTemplate }}';

    try {
        const payloadElement = document.getElementById('universalCrudConfigPayload');
        const payloadText = payloadElement ? (payloadElement.textContent || '{}') : '{}';
        window.universalCrudConfig = payloadText ? JSON.parse(payloadText) : {};
        console.log('[UniversalCrud] 配置解析成功', {
            hasSearchFields: !!(window.universalCrudConfig.search_fields && window.universalCrudConfig.search_fields.length > 0),
            searchFieldsCount: window.universalCrudConfig.search_fields?.length || 0,
            hasSearchFieldsConfig: !!(window.universalCrudConfig.search_fields_config && window.universalCrudConfig.search_fields_config.length > 0),
            searchFieldsConfigCount: window.universalCrudConfig.search_fields_config?.length || 0,
            features: window.universalCrudConfig.features
        });
    } catch (error) {
        console.error('[UniversalCrud] 解析配置失败', error);
        window.universalCrudConfig = {};
    }

    // 初始化搜索表单渲染器（如果启用了搜索功能）
    const enableSearch = {{ !empty($config['search_fields']) && ($features['search'] ?? true) ? 'true' : 'false' }};
    console.log('[UniversalCrud] 搜索功能检查', {
        enableSearch: enableSearch,
        hasSearchFields: {{ !empty($config['search_fields']) ? 'true' : 'false' }},
        searchFieldsCount: {{ count($config['search_fields'] ?? []) }},
        searchFeatureEnabled: {{ ($features['search'] ?? true) ? 'true' : 'false' }},
        hasSearchFormRenderer: typeof window.SearchFormRenderer !== 'undefined'
    });
    
    if (enableSearch && window.SearchFormRenderer) {
        // 等待数据表格组件初始化完成后再初始化搜索表单
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟初始化，确保数据表格组件已经创建了搜索面板容器
            setTimeout(function() {
                // 从全局配置中读取搜索配置
                const config = window.universalCrudConfig || {};
                const searchConfig = {
                    search_fields: config.search_fields || @json($config['search_fields'] ?? []),
                    search_fields_config: config.search_fields_config || @json($config['search_fields_config'] ?? []),
                };
                
                if (searchConfig.search_fields && searchConfig.search_fields.length > 0) {
                    try {
                        const renderer = new window.SearchFormRenderer({
                            config: searchConfig,
                            formId: 'searchForm_dataTable',
                            panelId: 'searchPanel_dataTable',
                            tableId: 'dataTable',
                            model: '{{ $model }}'
                        });
                        
                        // 保存渲染器实例，供重置函数使用
                        window['_searchFormRenderer_dataTable'] = renderer;
                        
                        // 创建重置函数
                        window['resetSearchForm_dataTable'] = function() {
                            if (renderer && typeof renderer.reset === 'function') {
                                renderer.reset();
                            }
                        };
                        
                        console.log('[UniversalCrud] 搜索表单渲染器已初始化', {
                            searchFields: searchConfig.search_fields,
                            fieldsConfigCount: searchConfig.search_fields_config?.length || 0
                        });
                    } catch (error) {
                        console.error('[UniversalCrud] 搜索表单渲染器初始化失败', error);
                    }
                } else {
                    console.log('[UniversalCrud] 未配置搜索字段，跳过搜索表单渲染');
                }
            }, 200); // 增加延迟时间，确保数据表格组件完全初始化
        });
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
            channel: '{{ $shellChannel }}',
            hideActions: true  // 隐藏"新标签"和"新窗口"按钮
        });
    }, true);

    // 初始化刷新父页面监听器（使用通用组件）
    // 自动监听来自 iframe 的 refreshParent 消息并刷新数据表
    initRefreshParentListener('dataTable', {
        channel: '{{ $shellChannel }}',  // 使用配置的频道
        logPrefix: '[UniversalCrud]'     // 日志前缀
    });
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
                    'emptyMessage' => '暂无数据',
                    'ajaxUrl' => $indexRoute,  // 启用 AJAX 模式
                    'searchFormId' => 'searchForm_dataTable',
                    'searchPanelId' => 'searchPanel_dataTable',
                    // 不传递 searchConfig，改为使用 JavaScript 渲染搜索表单
                    'model' => $model,  // 传递模型名称，用于关联模式字段的异步加载
                            // 创建页面路由（用于批量复制打开创建页时使用）
                            'createRoute' => $createRoute,
                            // iframe shell 通道（用于打开 iframe 时的频道前缀）
                            'iframeShellChannel' => $shellChannel,
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
                                'data-iframe-shell-hide-actions' => 'true',  // 隐藏"新标签"和"新窗口"按钮
                            ],
                        ] : null,
                        // 批量复制按钮：受 add 功能开关控制（用于基于选中项打开创建页并预填充）
                        ($features['add'] ?? true) ? [
                            'type' => 'button',
                            'text' => '复制',
                            'icon' => 'bi-files',
                            'variant' => 'primary',
                            'id' => 'batchCopyBtn_dataTable',
                            'onclick' => 'batchCopy_dataTable()',
                            'attributes' => [
                                'disabled' => 'disabled',
                            ],
                            'title' => '复制选中的记录'
                        ] : null,
                        // 删除按钮：需要有路由且受 delete 功能开关控制
                        (!empty($batchDestroyRoute) && ($features['delete'] ?? true)) ? [
                            'type' => 'button',
                            'text' => '删除',
                            'icon' => 'bi-trash',
                            'variant' => 'danger',
                            'id' => 'batchDeleteBtn_dataTable',
                            'onclick' => 'batchDelete_dataTable()',
                            'title' => '删除选中的记录'
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
                        // 回收站按钮：受 soft_delete 功能开关控制
                        ($features['soft_delete'] ?? false) ? [
                            'type' => 'link',
                            'href' => $trashRoute,
                            'icon' => 'bi-trash',
                            'text' => '回收站',
                            'title' => '查看已删除的数据',
                            'variant' => 'outline-secondary',
                            'attributes' => [
                                'data-iframe-shell-trigger' => 'universal-trash-' . $model,
                                'data-iframe-shell-src' => $trashRoute,
                                'data-iframe-shell-title' => ($config['title'] ?? '数据') . '回收站',
                                'data-iframe-shell-channel' => $shellChannel,
                                'data-iframe-shell-hide-actions' => 'true',
                            ],
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
