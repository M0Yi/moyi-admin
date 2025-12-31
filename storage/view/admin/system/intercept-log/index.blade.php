@extends('admin.layouts.admin')

@section('title', '拦截日志')

@php
    $interceptLogSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($interceptLogSearchConfig['search_fields'] ?? []);
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
        <h6 class="mb-1 fw-bold">拦截日志</h6>
        <small class="text-muted">查看系统拦截日志记录</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'interceptLogTable',
                'storageKey' => 'interceptLogTableColumns',
                'ajaxUrl' => admin_route('system/intercept-logs'),
                'searchFormId' => 'searchForm_interceptLogTable',
                'searchPanelId' => 'searchPanel_interceptLogTable',
                'searchConfig' => $interceptLogSearchConfig,
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
                        'label' => '拦截类型',
                        'field' => 'intercept_type',
                        'type' => 'custom',
                        'renderFunction' => 'renderInterceptType',
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
                        'label' => '拦截原因',
                        'field' => 'reason',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 7,
                        'label' => '执行时长',
                        'field' => 'duration',
                        'type' => 'custom',
                        'renderFunction' => 'renderDuration',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 8,
                        'label' => '所属站点',
                        'field' => 'site.name',
                        'type' => 'text',
                        'visible' => is_super_admin(),
                        'width' => '120',
                    ],
                    [
                        'index' => 9,
                        'label' => '拦截时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 10,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/intercept-logs') . '/{id}',
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '查看详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'intercept-log-show-{id}',
                                    'data-iframe-shell-src' => admin_route('system/intercept-logs') . '/{id}',
                                    'data-iframe-shell-title' => '拦截日志详情',
                                    'data-iframe-shell-channel' => 'intercept-log',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_interceptLogTable({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除',
                            ]
                        ],
                        'visible' => true,
                        'width' => '120',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无拦截日志',
                'batchActions' => [
                    [
                        'label' => '批量删除',
                        'action' => 'batchDelete_interceptLogTable',
                        'variant' => 'danger',
                        'icon' => 'bi-trash',
                        'confirm' => '确定要删除选中的记录吗？'
                    ]
                ]
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
// 渲染拦截类型
function renderInterceptType(value) {
    const labels = {
        '404': '页面不存在',
        'invalid_path': '非法路径',
        'unauthorized': '未授权访问'
    };
    const colors = {
        '404': 'warning',
        'invalid_path': 'danger',
        'unauthorized': 'danger'
    };
    const label = labels[value] || value;
    const color = colors[value] || 'secondary';
    return `<span class="badge bg-${color}">${label}</span>`;
}

// 渲染请求方法
function renderMethod(value) {
    const colors = {
        'GET': 'success',
        'POST': 'primary',
        'PUT': 'warning',
        'DELETE': 'danger',
        'PATCH': 'info',
        'HEAD': 'secondary',
        'OPTIONS': 'info'
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

// 批量删除函数
function batchDelete_interceptLogTable() {
    const table = window['_dataTable_interceptLogTable'];
    if (!table) {
        alert('表格未初始化');
        return;
    }

    const selectedRows = table.getSelectedRows();
    if (!selectedRows || selectedRows.length === 0) {
        alert('请选择要删除的记录');
        return;
    }

    const ids = selectedRows.map(row => row.id);
    if (!confirm(`确定要删除选中的 ${ids.length} 条记录吗？`)) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(`{{ admin_route("system/intercept-logs") }}/batch-destroy`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ ids })
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
}

document.addEventListener('DOMContentLoaded', function () {
    // 删除单行函数
    window.deleteRow_interceptLogTable = function(id) {
        if (!confirm('确定要删除这条记录吗？')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route("system/intercept-logs") }}/${id}`, {
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
                const table = window['_dataTable_interceptLogTable'];
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

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($interceptLogSearchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_interceptLogTable',
                panelId: 'searchPanel_interceptLogTable',
                tableId: 'interceptLogTable'
            });

            window['_searchFormRenderer_interceptLogTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_interceptLogTable = window.createSearchFormResetFunction('interceptLogTable');
            } else {
                window.resetSearchForm_interceptLogTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[InterceptLogPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
