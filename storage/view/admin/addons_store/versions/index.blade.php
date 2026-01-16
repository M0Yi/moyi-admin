@extends('admin.layouts.admin')

@section('title', '应用版本管理')

@php
    $searchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($searchConfig['search_fields'] ?? []);
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
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-bold">应用版本管理</h6>
                <small class="text-muted">管理系统中所有应用的版本</small>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'versionManagementTable',
                'storageKey' => 'versionManagementTableColumns',
                'ajaxUrl' => admin_route('addons_store/versions'),
                'searchFormId' => 'searchForm_versionManagementTable',
                'searchPanelId' => 'searchPanel_versionManagementTable',
                'searchConfig' => $searchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    [
                        'index' => 0,
                        'label' => '应用名称',
                        'field' => 'addon_name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 1,
                        'label' => '应用标识符',
                        'field' => 'addon_identifier',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '140',
                        'class' => 'text-monospace'
                    ],
                    [
                        'index' => 2,
                        'label' => '版本号',
                        'field' => 'version',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                        'class' => 'fw-bold text-primary'
                    ],
                    [
                        'index' => 3,
                        'label' => '当前安装版本',
                        'field' => 'current_version',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '130',
                        'class' => 'text-info'
                    ],
                    [
                        'index' => 4,
                        'label' => '应用分类',
                        'field' => 'addon_category',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonCategory',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 5,
                        'label' => '文件大小',
                        'field' => 'filesize',
                        'type' => 'custom',
                        'renderFunction' => 'formatFileSize',
                        'visible' => true,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 6,
                        'label' => '下载次数',
                        'field' => 'downloads',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 7,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'custom',
                        'renderFunction' => 'renderVersionStatus',
                        'visible' => true,
                        'width' => '80',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 8,
                        'label' => '发布时间',
                        'field' => 'released_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150'
                    ],
                    [
                        'index' => 9,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'button',
                                'onclick' => 'downloadVersion({id})',
                                'icon' => 'bi-download',
                                'variant' => 'primary',
                                'title' => '下载',
                                'condition' => '{status} == 1'
                            ],
                            [
                                'type' => 'link',
                                'href' => admin_route('addons_store/{addon_id}/versions'),
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '查看详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'version-detail-{id}',
                                    'data-iframe-shell-src' => admin_route('addons_store/{addon_id}/versions'),
                                    'data-iframe-shell-title' => '版本详情',
                                    'data-iframe-shell-channel' => 'version-management',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteVersion({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除'
                            ]
                        ],
                        'visible' => true,
                        'width' => '180',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无版本数据',
                'batchActions' => [
                    [
                        'label' => '批量删除',
                        'action' => 'batchDelete_versionManagementTable',
                        'variant' => 'danger',
                        'icon' => 'bi-trash',
                        'confirm' => '确定要删除选中的版本吗？'
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
// 渲染应用分类
function renderAddonCategory(value) {
    const labels = {
        'system': '系统',
        'tool': '工具',
        'theme': '主题',
        'other': '其他'
    };
    return labels[value] || value;
}

// 格式化文件大小
function formatFileSize(value) {
    if (!value) return '-';
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = parseInt(value);
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return size.toFixed(1) + ' ' + units[unitIndex];
}

// 渲染版本状态
function renderVersionStatus(value) {
    const statusMap = {
        1: { label: '启用', class: 'badge bg-success' },
        0: { label: '禁用', class: 'badge bg-secondary' }
    };

    const status = statusMap[value] || statusMap[0];
    return `<span class="${status.class}">${status.label}</span>`;
}

// 批量删除函数
function batchDelete_versionManagementTable() {
    const table = window['_dataTable_versionManagementTable'];
    if (!table) {
        alert('表格未初始化');
        return;
    }

    const selectedRows = table.getSelectedRows();
    if (!selectedRows || selectedRows.length === 0) {
        alert('请选择要删除的版本');
        return;
    }

    const ids = selectedRows.map(row => row.id);
    if (!confirm(`确定要删除选中的 ${ids.length} 条版本吗？`)) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(`{{ admin_route("addons_store/versions") }}/batch-destroy`, {
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
    // 下载版本
    window.downloadVersion = function(versionId) {
        const url = '{{ admin_route('addons_store/versions') }}/' + versionId + '/download';
        window.open(url, '_blank');
    };

    // 删除版本
    window.deleteVersion = function(versionId) {
        if (!confirm('确定要删除此版本吗？此操作不可恢复。')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route('addons_store/versions') }}/${versionId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert('删除成功');
                const table = window['_dataTable_versionManagementTable'];
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
    const config = @json($searchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_versionManagementTable',
                panelId: 'searchPanel_versionManagementTable',
                tableId: 'versionManagementTable'
            });

            window['_searchFormRenderer_versionManagementTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_versionManagementTable = window.createSearchFormResetFunction('versionManagementTable');
            } else {
                window.resetSearchForm_versionManagementTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[VersionManagementPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush