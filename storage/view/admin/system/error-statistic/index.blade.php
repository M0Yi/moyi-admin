@extends('admin.layouts.admin')

@section('title', '错误统计')

@php
    $errorStatisticSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($errorStatisticSearchConfig['search_fields'] ?? []);
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
        <h6 class="mb-1 fw-bold">错误统计</h6>
        <small class="text-muted">查看系统错误日志统计</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'errorStatisticTable',
                'storageKey' => 'errorStatisticTableColumns',
                'ajaxUrl' => admin_route('system/error-statistics'),
                'searchFormId' => 'searchForm_errorStatisticTable',
                'searchPanelId' => 'searchPanel_errorStatisticTable',
                'searchConfig' => $errorStatisticSearchConfig,
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
                        'label' => '异常类',
                        'field' => 'exception_class',
                        'type' => 'custom',
                        'renderFunction' => 'renderExceptionClass',
                        'visible' => true,
                        'width' => '200',
                    ],
                    [
                        'index' => 2,
                        'label' => '错误消息',
                        'field' => 'error_message',
                        'type' => 'text',
                        'visible' => true,
                        'maxLength' => 50,
                    ],
                    [
                        'index' => 3,
                        'label' => '错误等级',
                        'field' => 'error_level',
                        'type' => 'custom',
                        'renderFunction' => 'renderErrorLevel',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 4,
                        'label' => '状态码',
                        'field' => 'status_code',
                        'type' => 'custom',
                        'renderFunction' => 'renderStatusCode',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 5,
                        'label' => '请求路径',
                        'field' => 'request_path',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 6,
                        'label' => 'IP地址',
                        'field' => 'request_ip',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                    ],
                    [
                        'index' => 7,
                        'label' => '用户名',
                        'field' => 'username',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 8,
                        'label' => '发生次数',
                        'field' => 'occurrence_count',
                        'type' => 'custom',
                        'renderFunction' => 'renderOccurrenceCount',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 9,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'custom',
                        'renderFunction' => 'renderStatus',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 10,
                        'label' => '最后发生时间',
                        'field' => 'last_occurred_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 11,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/error-statistics') . '/{id}',
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '查看详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'error-statistic-show-{id}',
                                    'data-iframe-shell-src' => admin_route('system/error-statistics') . '/{id}',
                                    'data-iframe-shell-title' => '错误统计详情',
                                    'data-iframe-shell-channel' => 'error-statistic',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'resolveRow_errorStatisticTable({id})',
                                'icon' => 'bi-check-circle',
                                'variant' => 'success',
                                'title' => '标记为已解决',
                                'visible' => '{status} < 2'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_errorStatisticTable({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除',
                                'visible' => false
                            ]
                        ],
                        'visible' => true,
                        'width' => '150',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无错误统计记录',
                // 批量操作
                'batchActions' => [
                    [
                        'type' => 'button',
                        'onclick' => 'batchResolve_errorStatisticTable()',
                        'icon' => 'bi-check-circle',
                        'variant' => 'success',
                        'title' => '批量标记为已解决',
                        'confirm' => '确定要将选中的记录标记为已解决吗？'
                    ]
                ],
                'showBatchActions' => true,
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
// 渲染异常类
function renderExceptionClass(value) {
    if (!value) return '-';
    const shortName = value.split('\\').pop();
    return `<code title="${value}">${shortName}</code>`;
}

// 渲染错误等级
function renderErrorLevel(value) {
    const levelColors = {
        'error': 'danger',
        'warning': 'warning',
        'notice': 'info',
    };
    const color = levelColors[value] || 'secondary';
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

// 渲染发生次数
function renderOccurrenceCount(value) {
    if (!value) return '1';
    if (value > 100) {
        return `<span class="badge bg-danger">${value}</span>`;
    } else if (value > 10) {
        return `<span class="badge bg-warning">${value}</span>`;
    } else {
        return `<span class="badge bg-info">${value}</span>`;
    }
}

// 渲染状态
function renderStatus(value) {
    switch (parseInt(value)) {
        case 0:
            return `<span class="badge bg-danger">未处理</span>`;
        case 1:
            return `<span class="badge bg-warning">处理中</span>`;
        case 2:
            return `<span class="badge bg-success">已解决</span>`;
        default:
            return `<span class="badge bg-secondary">未知</span>`;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // 标记为已解决函数
    window.resolveRow_errorStatisticTable = function(id) {
        if (!confirm('确定要将此错误标记为已解决吗？')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route("system/error-statistics") }}/${id}/resolve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert(data.msg || '标记成功');
                const table = window['_dataTable_errorStatisticTable'];
                if (table) {
                    table.reload();
                }
            } else {
                alert(data.msg || data.message || '标记失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('标记失败');
        });
    };

    // 删除单行函数
    window.deleteRow_errorStatisticTable = function(id) {
        if (!confirm('确定要删除这条记录吗？')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route("system/error-statistics") }}/${id}`, {
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
                const table = window['_dataTable_errorStatisticTable'];
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

    // 批量标记为已解决
    window.batchResolve_errorStatisticTable = function() {
        const table = window['_dataTable_errorStatisticTable'];
        if (!table) {
            alert('表格未初始化');
            return;
        }

        const selectedRows = table.getSelectedRows();
        if (selectedRows.length === 0) {
            alert('请先选择要标记的记录');
            return;
        }

        const ids = selectedRows.map(row => row.id);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route("system/error-statistics") }}/batch-resolve`, {
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
                alert(data.msg || '批量标记成功');
                table.reload();
            } else {
                alert(data.msg || data.message || '批量标记失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('批量标记失败');
        });
    };

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($errorStatisticSearchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_errorStatisticTable',
                panelId: 'searchPanel_errorStatisticTable',
                tableId: 'errorStatisticTable'
            });

            window['_searchFormRenderer_errorStatisticTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_errorStatisticTable = window.createSearchFormResetFunction('errorStatisticTable');
            } else {
                window.resetSearchForm_errorStatisticTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[ErrorStatisticPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
