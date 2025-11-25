@extends('admin.layouts.admin')

@section('title', '角色管理')

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
        <h6 class="mb-1 fw-bold">角色管理</h6>
        <small class="text-muted">管理系统角色及其权限</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'roleTable',
                'storageKey' => 'roleTableColumns',
                'ajaxUrl' => admin_route('system/roles'),
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
                        'label' => '角色名称',
                        'field' => 'name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '角色标识',
                        'field' => 'slug',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 3,
                        'label' => '权限数量',
                        'field' => 'permissions',
                        'type' => 'custom',
                        'renderFunction' => 'renderRolePermissionsCount',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 4,
                        'label' => '排序',
                        'field' => 'sort',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '70',
                    ],
                    [
                        'index' => 5,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/roles') . '/{id}/toggle-status\', \'status\')',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 6,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 7,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/roles') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'role-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/roles') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑角色',
                                    'data-iframe-shell-channel' => 'role',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_roleTable({id})',
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
                'emptyMessage' => '暂无角色数据',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/roles/create'),
                        'text' => '新建角色',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'role-create',
                            'data-iframe-shell-src' => admin_route('system/roles/create'),
                            'data-iframe-shell-title' => '新建角色',
                            'data-iframe-shell-channel' => 'role',
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
<script>
(function() {
    'use strict';
    window.destroyRouteTemplate_roleTable = '{{ admin_route("system/roles") }}';
    initRefreshParentListener('roleTable', {
        logPrefix: '[Role]'
    });
})();

function renderRolePermissionsCount(value, column, row) {
    if (!value || !value.length) {
        return '<span class="badge bg-secondary">0</span>';
    }
    return `<span class="badge bg-info">${value.length}</span>`;
}
</script>
@endpush

