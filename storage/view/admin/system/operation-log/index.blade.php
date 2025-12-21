@extends('admin.layouts.admin')

@section('title', '操作日志')

@php
    $operationLogSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($operationLogSearchConfig['search_fields'] ?? []);
@endphp

@if (! ($isEmbedded ?? false))
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
<div class="container-fluid py-4">
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">操作日志</h6>
        <small class="text-muted">查看系统操作日志记录</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'operationLogTable',
                'storageKey' => 'operationLogTableColumns',
                'ajaxUrl' => admin_route('system/operation-logs'),
                'searchFormId' => 'searchForm_operationLogTable',
                'searchPanelId' => 'searchPanel_operationLogTable',
                'searchConfig' => $operationLogSearchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    [
                        'index' => 0,
                        'label' => 'ID',
                        'field' => 'id',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '50',
                    ],
                    [
                        'index' => 1,
                        'label' => '用户名',
                        'field' => 'username',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 2,
                        'label' => '请求方法',
                        'field' => 'method',
                        'type' => 'custom',
                        'renderFunction' => 'renderMethod',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 3,
                        'label' => '请求路径',
                        'field' => 'path',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 4,
                        'label' => 'IP地址',
                        'field' => 'ip',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                    ],
                    [
                        'index' => 5,
                        'label' => '状态码',
                        'field' => 'status_code',
                        'type' => 'custom',
                        'renderFunction' => 'renderStatusCode',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 6,
                        'label' => '执行时长',
                        'field' => 'duration',
                        'type' => 'custom',
                        'renderFunction' => 'renderDuration',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 7,
                        'label' => '所属站点',
                        'field' => 'site.name',
                        'type' => 'text',
                        'visible' => is_super_admin(),
                        'width' => '120',
                    ],
                    [
                        'index' => 8,
                        'label' => '操作时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 9,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/operation-logs') . '/{id}',
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '查看详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'operation-log-show-{id}',
                                    'data-iframe-shell-src' => admin_route('system/operation-logs') . '/{id}',
                                    'data-iframe-shell-title' => '操作日志详情',
                                    'data-iframe-shell-channel' => 'operation-log',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_operationLogTable({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除'
                            ]
                        ],
                        'visible' => true,
                        'width' => '120',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无操作日志',
                'leftButtons' => [
                    [
                        'type' => 'button',
                        'onclick' => 'batchDelete_operationLogTable()',
                        'text' => '批量删除',
                        'icon' => 'bi-trash',
                        'variant' => 'danger',
                    ]
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@if ($hasSearchConfig)
    @include('components.admin-script', ['path' => '/js/components/search-form-renderer.js'])
@endif
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
// 渲染请求方法
function renderMethod(value) {
    const colors = {
        'GET': 'success',
        'POST': 'primary',
        'PUT': 'warning',
        'DELETE': 'danger',
        'PATCH': 'info',
    };
    const color = colors[value] || 'secondary';
    return `<span class="badge bg-${color}">${value}</span>`;
}

// 渲染状态码
function renderStatusCode(value) {
    if (!value) return '-';
    let color = 'secondary';
    if (value >= 200 && value < 300) {
        color = 'success';
    } else if (value >= 300 && value < 400) {
        color = 'info';
    } else if (value >= 400 && value < 500) {
        color = 'warning';
    } else if (value >= 500) {
        color = 'danger';
    }
    return `<span class="badge bg-${color}">${value}</span>`;
}

// 渲染执行时长
function renderDuration(value) {
    if (!value) return '-';
    if (value < 100) {
        return `<span class="text-success">${value}ms</span>`;
    } else if (value < 500) {
        return `<span class="text-warning">${value}ms</span>`;
    } else {
        return `<span class="text-danger">${value}ms</span>`;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // 批量删除函数
    window.batchDelete_operationLogTable = function() {
        const table = window['_dataTable_operationLogTable'];
        if (!table) {
            alert('表格未初始化');
            return;
        }
        
        const selectedRows = table.getSelectedRows();
        if (!selectedRows || selectedRows.length === 0) {
            alert('请选择要删除的记录');
            return;
        }
        
        if (!confirm(`确定要删除选中的 ${selectedRows.length} 条记录吗？`)) {
            return;
        }
        
        const ids = selectedRows.map(row => row.id);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('{{ admin_route("system/operation-logs") }}/batch-destroy', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ ids: ids })
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert(data.msg || '删除成功');
                table.reload();
            } else {
                alert(data.msg || data.message || '删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除失败');
        });
    };
    
    // 删除单行函数
    window.deleteRow_operationLogTable = function(id) {
        if (!confirm('确定要删除这条记录吗？')) {
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch(`{{ admin_route("system/operation-logs") }}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert(data.msg || '删除成功');
                const table = window['_dataTable_operationLogTable'];
                if (table) {
                    table.reload();
                }
            } else {
                alert(data.msg || data.message || '删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除失败');
        });
    };
});
</script>
@if ($hasSearchConfig)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const config = @json($operationLogSearchConfig);
    if (!config || !config.search_fields || !config.search_fields.length) {
        return;
    }
    if (typeof window.SearchFormRenderer !== 'function') {
        console.warn('[OperationLogPage] SearchFormRenderer 未加载');
        return;
    }

    const renderer = new window.SearchFormRenderer({
        config,
        formId: 'searchForm_operationLogTable',
        panelId: 'searchPanel_operationLogTable',
        tableId: 'operationLogTable'
    });

    window['_searchFormRenderer_operationLogTable'] = renderer;
    if (typeof window.createSearchFormResetFunction === 'function') {
        window.resetSearchForm_operationLogTable = window.createSearchFormResetFunction('operationLogTable');
    } else {
        window.resetSearchForm_operationLogTable = function () {
            if (renderer && typeof renderer.reset === 'function') {
                renderer.reset();
            }
        };
    }
});
</script>
@endif
@endpush

