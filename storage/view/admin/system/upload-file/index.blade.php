@extends('admin.layouts.admin')

@section('title', '文件管理')

@php
    $uploadFileSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($uploadFileSearchConfig['search_fields'] ?? []);
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
        <h6 class="mb-1 fw-bold">文件管理</h6>
        <small class="text-muted">管理系统上传的文件</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'uploadFileTable',
                'storageKey' => 'uploadFileTableColumns',
                'ajaxUrl' => admin_route('system/upload-files'),
                'searchFormId' => 'searchForm_uploadFileTable',
                'searchPanelId' => 'searchPanel_uploadFileTable',
                'searchConfig' => $uploadFileSearchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'defaultPageSize' => 20,
                'pageSizeOptions' => [10, 20, 50, 100],
                'columns' => [
                    [
                        'index' => 0,
                        'label' => 'ID',
                        'field' => 'id',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '60',
                    ],
                    [
                        'index' => 1,
                        'label' => '预览',
                        'field' => 'file_url',
                        'type' => 'custom',
                        'renderFunction' => 'renderFilePreview',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 2,
                        'label' => '原始文件名',
                        'field' => 'original_filename',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 3,
                        'label' => '文件名',
                        'field' => 'filename',
                        'type' => 'text',
                        'visible' => false,
                    ],
                    [
                        'index' => 4,
                        'label' => '文件类型',
                        'field' => 'content_type',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                    ],
                    [
                        'index' => 5,
                        'label' => '文件大小',
                        'field' => 'file_size_formatted',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 6,
                        'label' => '上传用户',
                        'field' => 'username',
                        'type' => 'text',
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
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'badge',
                        'badgeMap' => [
                            '0' => ['text' => '待上传', 'variant' => 'secondary'],
                            '1' => ['text' => '已上传', 'variant' => 'success'],
                            '2' => ['text' => '违规', 'variant' => 'danger'],
                            '3' => ['text' => '已删除', 'variant' => 'dark'],
                        ],
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 9,
                        'label' => '审核状态',
                        'field' => 'check_status',
                        'type' => 'badge',
                        'badgeMap' => [
                            '0' => ['text' => '待审核', 'variant' => 'warning'],
                            '1' => ['text' => '通过', 'variant' => 'success'],
                            '2' => ['text' => '违规', 'variant' => 'danger'],
                        ],
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 10,
                        'label' => '存储驱动',
                        'field' => 'storage_driver',
                        'type' => 'text',
                        'visible' => false,
                        'width' => '100',
                    ],
                    [
                        'index' => 11,
                        'label' => '上传时间',
                        'field' => 'uploaded_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 12,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 13,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/upload-files') . '/{id}',
                                'icon' => 'bi-info-circle',
                                'variant' => 'secondary',
                                'title' => '详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'file-detail-{id}',
                                    'data-iframe-shell-src' => admin_route('system/upload-files') . '/{id}',
                                    'data-iframe-shell-title' => '文件详情',
                                    'data-iframe-shell-channel' => 'upload-files'
                                ]
                            ],
                            [
                                'type' => 'link',
                                'href' => admin_route('system/upload-files') . '/{id}/preview',
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '预览',
                                'visible' => function($row) {
                                    return !empty($row['file_url'] ?? '');
                                },
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'file-preview-{id}',
                                    'data-iframe-shell-src' => admin_route('system/upload-files') . '/{id}/preview',
                                    'data-iframe-shell-title' => '文件预览',
                                    'data-iframe-shell-channel' => 'upload-files'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_uploadFileTable({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除'
                            ],
                        ],
                        'visible' => true,
                        'width' => '120',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无文件数据',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/upload-files') . '/create',
                        'text' => '上传文件',
                        'icon' => 'bi-upload',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'upload-file-create',
                            'data-iframe-shell-src' => admin_route('system/upload-files') . '/create',
                            'data-iframe-shell-title' => '上传文件',
                            'data-iframe-shell-channel' => 'upload-files',
                            'data-iframe-shell-auto-close' => 'true',
                        ],
                    ],
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
@include('components.admin-script', ['path' => '/js/admin/system/upload-file-page.js'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 注册自定义渲染函数到全局作用域
    window.renderFilePreview = renderFilePreview;
    
    if (window.UploadFilePage && typeof window.UploadFilePage.initList === 'function') {
        window.UploadFilePage.initList({
            tableId: 'uploadFileTable',
            destroyRoute: '{{ admin_route("system/upload-files") }}',
            logPrefix: '[UploadFile]'
        });
    } else {
        console.warn('[UploadFilePage] initList 未定义');
    }
    
    // 初始化刷新父页面监听器（监听来自 iframe 的刷新消息）
    if (typeof initRefreshParentListener === 'function') {
        initRefreshParentListener('uploadFileTable', {
            channel: 'upload-files',
            logPrefix: '[UploadFile]'
        });
    }
});

// 文件预览渲染函数
// 注意：data-table-with-columns 会以 (value, column, row) 形式调用自定义渲染函数
function renderFilePreview(value, column, row) {
    const fileUrl = row && row.file_url ? row.file_url : '';
    const contentType = row && row.content_type ? row.content_type : '';
    const originalFilename = row && row.original_filename ? row.original_filename : '';
    
    if (!fileUrl) {
        return '<span class="text-muted">-</span>';
    }
    
    // 判断是否为图片
    const isImage = contentType && contentType.startsWith('image/');
    
    if (isImage) {
        const previewUrl = '{{ admin_route("system/upload-files") }}/' + row.id + '/preview';
        const safeFilename = (originalFilename || '').replace(/['"]/g, '&quot;');
        return `<img src="${fileUrl}" alt="${safeFilename}" 
                     class="img-thumbnail" 
                     style="max-width: 60px; max-height: 60px; cursor: pointer; object-fit: cover;"
                     data-iframe-shell-trigger="file-preview-img-${row.id}"
                     data-iframe-shell-src="${previewUrl}"
                     data-iframe-shell-title="文件预览 - ${safeFilename}"
                     data-iframe-shell-channel="upload-files"
                     title="点击查看大图">`;
    }
    
    // 根据文件类型显示图标
    let icon = 'bi-file-earmark';
    if (contentType) {
        if (contentType.includes('pdf')) {
            icon = 'bi-file-pdf';
        } else if (contentType.includes('word') || contentType.includes('document')) {
            icon = 'bi-file-word';
        } else if (contentType.includes('excel') || contentType.includes('spreadsheet')) {
            icon = 'bi-file-excel';
        } else if (contentType.includes('zip') || contentType.includes('rar')) {
            icon = 'bi-file-zip';
        } else if (contentType.includes('video')) {
            icon = 'bi-file-play';
        } else if (contentType.includes('audio')) {
            icon = 'bi-file-music';
        }
    }
    
    const previewUrl = '{{ admin_route("system/upload-files") }}/' + row.id + '/preview';
    const safeFilename = (originalFilename || '').replace(/['"]/g, '&quot;');
    return `<i class="bi ${icon} fs-4 text-secondary" 
               style="cursor: pointer;"
               data-iframe-shell-trigger="file-preview-icon-${row.id}"
               data-iframe-shell-src="${previewUrl}"
               data-iframe-shell-title="文件预览 - ${safeFilename}"
               data-iframe-shell-channel="upload-files"
               title="点击查看文件"></i>`;
}


</script>
@if ($hasSearchConfig)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const config = @json($uploadFileSearchConfig);
    if (!config || !config.search_fields || !config.search_fields.length) {
        return;
    }
    if (typeof window.SearchFormRenderer !== 'function') {
        console.warn('[UploadFilePage] SearchFormRenderer 未加载');
        return;
    }

    const renderer = new window.SearchFormRenderer({
        config,
        formId: 'searchForm_uploadFileTable',
        panelId: 'searchPanel_uploadFileTable',
        tableId: 'uploadFileTable'
    });

    window['_searchFormRenderer_uploadFileTable'] = renderer;
    if (typeof window.createSearchFormResetFunction === 'function') {
        window.resetSearchForm_uploadFileTable = window.createSearchFormResetFunction('uploadFileTable');
    } else {
        window.resetSearchForm_uploadFileTable = function () {
            if (renderer && typeof renderer.reset === 'function') {
                renderer.reset();
            }
        };
    }
});
</script>
@endif
@endpush

