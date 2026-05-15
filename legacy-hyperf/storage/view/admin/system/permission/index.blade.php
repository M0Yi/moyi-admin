@extends('admin.layouts.admin')

@section('title', '权限管理')

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
        <h6 class="mb-1 fw-bold">权限管理</h6>
        <small class="text-muted">管理系统权限资源</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'permissionTable',
                'storageKey' => 'permissionTableColumns',
                'ajaxUrl' => admin_route('system/permissions'),
                'showPagination' => false, // 权限管理树形结构不需要分页
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
                        'label' => '权限名称',
                        'field' => 'name',
                        'type' => 'custom',
                        'renderFunction' => 'renderPermissionName',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '权限标识',
                        'field' => 'slug',
                        'type' => 'code',
                        'visible' => true,
                        'width' => '200',
                    ],
                    [
                        'index' => 3,
                        'label' => '类型',
                        'field' => 'type',
                        'type' => 'badge',
                        'badgeMap' => [
                            'menu' => ['text' => '菜单', 'variant' => 'primary'],
                            'button' => ['text' => '按钮', 'variant' => 'info'],
                        ],
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 4,
                        'label' => '请求路径',
                        'field' => 'path',
                        'type' => 'code',
                        'visible' => true,
                        'width' => '200',
                    ],
                    [
                        'index' => 5,
                        'label' => '请求方法',
                        'field' => 'method',
                        'type' => 'badge',
                        'badgeMap' => [
                            '*' => ['text' => '任意', 'variant' => 'secondary'],
                            'GET' => ['text' => 'GET', 'variant' => 'primary'],
                            'POST' => ['text' => 'POST', 'variant' => 'success'],
                            'PUT' => ['text' => 'PUT', 'variant' => 'warning'],
                            'DELETE' => ['text' => 'DELETE', 'variant' => 'danger'],
                            'PATCH' => ['text' => 'PATCH', 'variant' => 'info'],
                        ],
                        'visible' => true,
                        'width' => '90',
                    ],
                    [
                        'index' => 6,
                        'label' => '排序',
                        'field' => 'sort',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '70',
                    ],
                    [
                        'index' => 7,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/permissions') . '/{id}/toggle-status\', \'status\')',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 8,
                        'label' => '创建时间',
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
                                'href' => admin_route('system/permissions') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'permission-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/permissions') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑权限',
                                    'data-iframe-shell-channel' => 'permission',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_permissionTable({id})',
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
                'emptyMessage' => '暂无权限数据',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/permissions/create'),
                        'text' => '新建权限',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'permission-create',
                            'data-iframe-shell-src' => admin_route('system/permissions/create'),
                            'data-iframe-shell-title' => '新建权限',
                            'data-iframe-shell-channel' => 'permission',
                            'data-iframe-shell-hide-actions' => 'true'
                        ]
                    ]
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@include('components.admin-script', ['path' => '/js/admin/system/permission-page.js'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.PermissionPage && typeof window.PermissionPage.initList === 'function') {
        window.PermissionPage.initList({
            tableId: 'permissionTable',
            destroyRoute: '{{ admin_route("system/permissions") }}',
        logPrefix: '[Permission]'
    });
    } else {
        console.warn('[PermissionPage] initList 未定义');
}
});
</script>
@endpush

