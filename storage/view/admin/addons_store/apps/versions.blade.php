@extends('admin.layouts.admin')

@section('title', '应用版本 - ' . ($addon['name'] ?? ''))

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
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ admin_route('addons_store') }}" class="text-decoration-none">
                        <i class="bi bi-house"></i> 应用管理
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">应用版本</li>
            </ol>
        </nav>
    </div>

    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-bold">{{ $addon['name'] ?? '未知插件' }}</h6>
                <small class="text-muted">{{ $addon['description'] ?? '' }}</small>
            </div>
            <div class="text-end">
                <small class="text-muted">当前版本: {{ $addon['version'] ?? 'N/A' }}</small><br>
                <small class="text-muted">作者: {{ $addon['author'] ?? '未知' }}</small>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'addonVersionsTable',
                'storageKey' => 'addonVersionsTableColumns',
                'ajaxUrl' => admin_route('addons_store/versions'),
                'searchFormId' => 'searchForm_addonVersionsTable',
                'searchPanelId' => 'searchPanel_addonVersionsTable',
                'searchConfig' => $searchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    [
                        'index' => 0,
                        'label' => '版本号',
                        'field' => 'version',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                        'class' => 'fw-bold text-primary'
                    ],
                    [
                        'index' => 1,
                        'label' => '描述',
                        'field' => 'description',
                        'type' => 'text',
                        'visible' => true,
                        'renderFunction' => 'renderVersionDescription'
                    ],
                    [
                        'index' => 2,
                        'label' => '文件大小',
                        'field' => 'filesize',
                        'type' => 'custom',
                        'renderFunction' => 'formatFileSize',
                        'visible' => true,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 3,
                        'label' => '下载次数',
                        'field' => 'downloads',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 4,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'custom',
                        'renderFunction' => 'renderVersionStatus',
                        'visible' => true,
                        'width' => '80',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 5,
                        'label' => '发布时间',
                        'field' => 'released_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150'
                    ],
                    [
                        'index' => 6,
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
                                'type' => 'button',
                                'onclick' => 'deleteVersion({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除',
                                'condition' => '{status} == 0'
                            ]
                        ],
                        'visible' => true,
                        'width' => '120',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无版本数据',
                'leftButtons' => [
                    [
                        'type' => 'button',
                        'text' => '刷新',
                        'icon' => 'bi-arrow-clockwise',
                        'variant' => 'outline-secondary',
                        'attributes' => [
                            'onclick' => 'refreshVersions()'
                        ]
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
// 自定义渲染函数
function renderVersionDescription(value) {
    if (!value) return '<span class="text-muted">无描述</span>';
    return value.length > 50 ? value.substring(0, 50) + '...' : value;
}

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

function renderVersionStatus(value) {
    const statusMap = {
        1: { label: '启用', class: 'badge bg-success' },
        0: { label: '禁用', class: 'badge bg-secondary' }
    };

    const status = statusMap[value] || statusMap[0];
    return `<span class="${status.class}">${status.label}</span>`;
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
                const table = window['_dataTable_addonVersionsTable'];
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

    // 刷新版本列表
    window.refreshVersions = function() {
        const table = window['_dataTable_addonVersionsTable'];
        if (table) {
            table.reload();
        }
    };

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($searchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_addonVersionsTable',
                panelId: 'searchPanel_addonVersionsTable',
                tableId: 'addonVersionsTable'
            });

            window['_searchFormRenderer_addonVersionsTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_addonVersionsTable = window.createSearchFormResetFunction('addonVersionsTable');
            } else {
                window.resetSearchForm_addonVersionsTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[AddonVersionsPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
