@extends('admin.layouts.admin')

@section('title', ($config['title'] ?? '数据') . '回收站')

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
    $trashRoute = admin_route("{$baseRoute}/trash");              // 回收站页面/API
    $indexRoute = admin_route($baseRoute);                        // 返回列表页
    $restoreRouteTemplate = admin_route($baseRoute);              // 恢复路由模板（需要拼接 /{id}/restore）
    $forceDeleteRouteTemplate = admin_route($baseRoute);          // 永久删除路由模板（需要拼接 /{id}/force-delete）
    $batchRestoreRoute = admin_route("{$baseRoute}/batch-restore");  // 批量恢复
    $batchForceDeleteRoute = admin_route("{$baseRoute}/batch-force-delete");  // 批量永久删除
    $clearTrashRoute = admin_route("{$baseRoute}/clear-trash");  // 清空回收站
    
    // 本地存储键名（用于保存列显示设置）
    $storageKey = 'universal_' . $model . '_trash_columns';
    
    // 功能开关配置：回收站只读模式（不允许恢复和删除）
    $featureDefaults = [
        'search' => true,
        'restore' => true,      // 恢复功能
        'force_delete' => true, // 永久删除功能
        'export' => false,      // 回收站默认不导出
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
        'view' => 'storage/view/admin/system/universal/trash.blade.php'
    ];
    if (isset($config['model_class'])) {
        $codeViewerFiles['model'] = str_replace('\\', '/', str_replace('App\\', 'app/', $config['model_class'])) . '.php';
    }

    $shellChannel = 'universal-trash-' . preg_replace('/[^a-z0-9\-]/', '-', strtolower($model));
@endphp

{{-- 配置代码查看器文件路径和删除路由模板 --}}
@push('admin_scripts')
<script type="application/json" id="universalCrudTrashConfigPayload">
{!! $configJson ?? '{}' !!}
</script>
<script src="/js/components/search-form-renderer.js"></script>
<script>
(function () {
    window.CODE_VIEWER_FILES = @json($codeViewerFiles);
    // 设置永久删除路由模板（供组件使用，不需要等待 DOM 就绪）
    window.forceDeleteRouteTemplate_dataTable = '{{ $forceDeleteRouteTemplate }}';

    try {
        const payloadElement = document.getElementById('universalCrudTrashConfigPayload');
        const payloadText = payloadElement ? (payloadElement.textContent || '{}') : '{}';
        window.universalCrudTrashConfig = payloadText ? JSON.parse(payloadText) : {};
        console.log('[UniversalCrudTrash] 配置解析成功', {
            hasSearchFields: !!(window.universalCrudTrashConfig.search_fields && window.universalCrudTrashConfig.search_fields.length > 0),
            searchFieldsCount: window.universalCrudTrashConfig.search_fields?.length || 0,
            features: window.universalCrudTrashConfig.features
        });
    } catch (error) {
        console.error('[UniversalCrudTrash] 解析配置失败', error);
        window.universalCrudTrashConfig = {};
    }

    // 初始化搜索表单渲染器（如果启用了搜索功能）
    const enableSearch = {{ !empty($config['search_fields']) && ($features['search'] ?? true) ? 'true' : 'false' }};
    console.log('[UniversalCrudTrash] 搜索功能检查', {
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
                const config = window.universalCrudTrashConfig || {};
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
                        
                        console.log('[UniversalCrudTrash] 搜索表单渲染器已初始化', {
                            searchFields: searchConfig.search_fields,
                            fieldsConfigCount: searchConfig.search_fields_config?.length || 0
                        });
                    } catch (error) {
                        console.error('[UniversalCrudTrash] 搜索表单渲染器初始化失败', error);
                    }
                } else {
                    console.log('[UniversalCrudTrash] 未配置搜索字段，跳过搜索表单渲染');
                }
            }, 200); // 增加延迟时间，确保数据表格组件完全初始化
        });
    }

    // 监听来自 iframe 的消息，当收到 refreshParent: true 时刷新数据表
    window.addEventListener('message', function(event) {
        // 安全检查：只处理同源消息
        if (event.origin !== window.location.origin) {
            return;
        }

        const data = event.data;
        if (!data || typeof data !== 'object') {
            return;
        }

        // 检查频道是否匹配（可选，如果设置了频道则必须匹配）
        const channel = '{{ $shellChannel }}';
        if (data.channel && data.channel !== channel) {
            return;
        }

        // 检查 payload 中是否包含 refreshParent: true
        const payload = data.payload;
        if (payload && typeof payload === 'object' && payload.refreshParent === true) {
            console.log('[UniversalCrudTrash] 收到 refreshParent 消息，刷新数据表', {
                action: data.action,
                source: data.source,
                payload: payload
            });
            
            // 检查 loadData_dataTable 函数是否存在
            if (typeof window.loadData_dataTable === 'function') {
                window.loadData_dataTable();
                console.log('[UniversalCrudTrash] 已触发 loadData_dataTable() 刷新数据表');
            } else {
                console.warn('[UniversalCrudTrash] loadData_dataTable 函数不存在，无法刷新数据表');
            }
        }
    });

    // 同时监听自定义事件（用于 iframe-shell 在顶层窗口时触发）
    window.addEventListener('refreshParent', function(event) {
        const payload = event.detail;
        if (payload && typeof payload === 'object' && payload.refreshParent === true) {
            console.log('[UniversalCrudTrash] 收到 refreshParent 自定义事件，刷新数据表');
            
            if (typeof window.loadData_dataTable === 'function') {
                window.loadData_dataTable();
                console.log('[UniversalCrudTrash] 已触发 loadData_dataTable() 刷新数据表');
            } else {
                console.warn('[UniversalCrudTrash] loadData_dataTable 函数不存在，无法刷新数据表');
            }
        }
    });

    // 恢复单个记录
    window.restoreData_dataTable = function(id) {
        if (!confirm('确定要恢复这条记录吗？')) {
            return;
        }

        const restoreUrl = '{{ $restoreRouteTemplate }}/' + id + '/restore';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(restoreUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                showToast('success', data.msg || data.message || '恢复成功');
                if (typeof window.loadData_dataTable === 'function') {
                    window.loadData_dataTable();
                }
            } else {
                showToast('danger', data.msg || data.message || '恢复失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('danger', '恢复失败');
        });
    };

    // 永久删除单个记录
    window.forceDeleteData_dataTable = function(id) {
        if (!confirm('确定要永久删除这条记录吗？此操作不可恢复！')) {
            return;
        }

        const deleteUrl = '{{ $forceDeleteRouteTemplate }}/' + id + '/force-delete';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                showToast('success', data.msg || data.message || '永久删除成功');
                if (typeof window.loadData_dataTable === 'function') {
                    window.loadData_dataTable();
                }
            } else {
                showToast('danger', data.msg || data.message || '永久删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('danger', '永久删除失败');
        });
    };

    // 批量恢复
    window.batchRestore_dataTable = function() {
        const selectedIds = window.getSelectedIds_dataTable ? window.getSelectedIds_dataTable() : [];
        if (selectedIds.length === 0) {
            showToast('warning', '请先选择要恢复的记录');
            return;
        }

        if (!confirm('确定要恢复选中的 ' + selectedIds.length + ' 条记录吗？')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('{{ $batchRestoreRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                showToast('success', data.msg || data.message || '批量恢复成功');
                if (typeof window.loadData_dataTable === 'function') {
                    window.loadData_dataTable();
                }
            } else {
                showToast('danger', data.msg || data.message || '批量恢复失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('danger', '批量恢复失败');
        });
    };

    // 批量永久删除
    window.batchForceDelete_dataTable = function() {
        const selectedIds = window.getSelectedIds_dataTable ? window.getSelectedIds_dataTable() : [];
        if (selectedIds.length === 0) {
            showToast('warning', '请先选择要永久删除的记录');
            return;
        }

        if (!confirm('确定要永久删除选中的 ' + selectedIds.length + ' 条记录吗？此操作不可恢复！')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('{{ $batchForceDeleteRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                showToast('success', data.msg || data.message || '批量永久删除成功');
                if (typeof window.loadData_dataTable === 'function') {
                    window.loadData_dataTable();
                }
            } else {
                showToast('danger', data.msg || data.message || '批量永久删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('danger', '批量永久删除失败');
        });
    };

    // 清空回收站
    window.clearTrash_dataTable = function() {
        if (!confirm('确定要清空回收站吗？此操作将永久删除所有已删除的记录，且不可恢复！')) {
            return;
        }

        // 二次确认
        if (!confirm('请再次确认：此操作将永久删除回收站中的所有记录，且无法恢复！')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('{{ $clearTrashRoute }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                showToast('success', data.msg || data.message || '清空回收站成功');
                if (typeof window.loadData_dataTable === 'function') {
                    window.loadData_dataTable();
                }
            } else {
                showToast('danger', data.msg || data.message || '清空回收站失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('danger', '清空回收站失败');
        });
    };
})();
</script>
@endpush

<div class="container-fluid py-4">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">
            <i class="bi bi-trash me-2"></i>{{ $config['title'] ?? '数据' }}回收站
        </h6>
        <small class="text-muted">管理已删除的{{ $config['title'] ?? '数据' }}，可以恢复或永久删除</small>
    </div>

    <!-- 数据列表卡片 -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            {{-- 使用集成了工具栏和搜索表单的数据表格组件（AJAX 模式） --}}
            <div id="dataTableContainer">
                @include('admin.components.data-table-with-columns', [
                    'tableId' => 'dataTable',
                    'storageKey' => $storageKey,
                    'emptyMessage' => '回收站暂无数据',
                    'ajaxUrl' => $trashRoute . '?_ajax=1',  // 启用 AJAX 模式，添加 _ajax=1 参数
                    'searchFormId' => 'searchForm_dataTable',
                    'searchPanelId' => 'searchPanel_dataTable',
                    'model' => $model,  // 传递模型名称，用于关联模式字段的异步加载
                    'batchDestroyRoute' => 'dummy',  // 设置为虚拟值以启用勾选列（用于批量恢复和批量永久删除），但不启用批量删除功能
                    'defaultPageSize' => $config['default_page_size'] ?? 15,  // 默认分页尺寸
                    'pageSizeOptions' => $config['page_size_options'] ?? [10, 15, 20, 50, 100],  // 可选的每页数量选项
                    'enablePageSizeStorage' => $config['enable_page_size_storage'] ?? true,  // 是否保存分页尺寸到 localStorage
                    'defaultSortField' => 'deleted_at',  // 默认按删除时间排序
                    'defaultSortOrder' => 'desc',  // 默认降序
                    'editRouteTemplate' => null,  // 回收站不允许编辑
                    'actionColumnConfig' => [
                        'enabled' => true,  // 启用操作列
                        'label' => '操作',
                        'width' => '150',
                        'actions' => array_filter([
                            // 恢复按钮：受 restore 功能开关控制
                            ($features['restore'] ?? true) ? [
                                'type' => 'button',
                                'onclick' => 'restoreData_dataTable({id})',
                                'icon' => 'bi-arrow-counterclockwise',
                                'variant' => 'success',
                                'title' => '恢复这条记录',
                                'visible' => true,
                            ] : null,
                            // 永久删除按钮：受 force_delete 功能开关控制
                            ($features['force_delete'] ?? true) ? [
                                'type' => 'button',
                                'onclick' => 'forceDeleteData_dataTable({id})',
                                'icon' => 'bi-trash-fill',
                                'variant' => 'danger',
                                'title' => '永久删除这条记录（不可恢复）',
                                'visible' => true,
                            ] : null,
                        ], function($item) {
                            return $item !== null;
                        }),
                    ],
                    'exportRoute' => null,  // 回收站默认不导出
                    'showPagination' => true,
                    'leftButtons' => array_filter([
                        // 批量恢复按钮：受 restore 功能开关控制
                        ($features['restore'] ?? true) ? [
                            'type' => 'button',
                            'text' => '批量恢复',
                            'icon' => 'bi-arrow-counterclockwise',
                            'variant' => 'success',
                            'id' => 'batchRestoreBtn_dataTable',
                            'onclick' => 'batchRestore_dataTable()',
                            'title' => '批量恢复选中的记录'
                        ] : null,
                        // 批量永久删除按钮：受 force_delete 功能开关控制
                        ($features['force_delete'] ?? true) ? [
                            'type' => 'button',
                            'text' => '批量永久删除',
                            'icon' => 'bi-trash-fill',
                            'variant' => 'danger',
                            'id' => 'batchForceDeleteBtn_dataTable',
                            'onclick' => 'batchForceDelete_dataTable()',
                            'title' => '批量永久删除选中的记录（不可恢复）'
                        ] : null,
                        // 清空回收站按钮：受 force_delete 功能开关控制
                        ($features['force_delete'] ?? true) ? [
                            'type' => 'button',
                            'text' => '清空回收站',
                            'icon' => 'bi-x-circle-fill',
                            'variant' => 'danger',
                            'id' => 'clearTrashBtn_dataTable',
                            'onclick' => 'clearTrash_dataTable()',
                            'title' => '清空回收站中的所有记录（不可恢复）'
                        ] : null,
                    ], function($item) {
                        return $item !== null;
                    }),
                    'rightButtons' => array_filter([
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

