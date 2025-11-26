@extends('admin.layouts.admin')

@section('title', '用户管理')

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
        <h6 class="mb-1 fw-bold">用户管理</h6>
        <small class="text-muted">管理系统后台用户</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'userTable',
                'storageKey' => 'userTableColumns',
                'ajaxUrl' => admin_route('system/users'),
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
                    ],
                    [
                        'index' => 2,
                        'label' => '真实姓名',
                        'field' => 'real_name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 3,
                        'label' => '角色',
                        'field' => 'roles',
                        'type' => 'custom',
                        'renderFunction' => 'renderUserRoles',
                        'visible' => true,
                    ],
                    [
                        'index' => 4,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/users') . '/{id}/toggle-status\', \'status\')',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 5,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 6,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/users') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'user-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/users') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑用户',
                                    'data-iframe-shell-channel' => 'user',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_userTable({id})',
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
                'emptyMessage' => '暂无用户数据',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/users/create'),
                        'text' => '新建用户',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'user-create',
                            'data-iframe-shell-src' => admin_route('system/users/create'),
                            'data-iframe-shell-title' => '新建用户',
                            'data-iframe-shell-channel' => 'user',
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
@include('components.admin-script', ['path' => '/js/admin/system/user-page.js'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.UserPage && typeof window.UserPage.initList === 'function') {
        window.UserPage.initList({
            tableId: 'userTable',
            destroyRoute: '{{ admin_route("system/users") }}',
        logPrefix: '[User]'
    });
    } else {
        console.warn('[UserPage] initList 未定义');
}
});
</script>
@endpush

