@extends('admin.layouts.admin')

@section('title', '插件管理')

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
        <h6 class="mb-1 fw-bold">插件管理</h6>
        <small class="text-muted">管理系统插件，自动扫描 addons 目录。启用插件将自动完成安装和路由加载，禁用插件将清理相关文件。</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'addonTable',
                'storageKey' => 'addonTableColumns',
                'ajaxUrl' => admin_route('system/addons'),
                'showPagination' => false,
                'columns' => [
                    [
                        'index' => 0,
                        'label' => 'ID',
                        'field' => 'id',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 1,
                        'label' => '插件名称',
                        'field' => 'name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '版本',
                        'field' => 'version',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 3,
                        'label' => '描述',
                        'field' => 'description',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 4,
                        'label' => '作者',
                        'field' => 'author',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                    ],
                    [
                        'index' => 5,
                        'label' => '类型',
                        'field' => 'type',
                        'type' => 'badge',
                        'badgeVariant' => 'info',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 6,
                        'label' => '分类',
                        'field' => 'category',
                        'type' => 'badge',
                        'badgeVariant' => 'secondary',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 7,
                        'label' => '状态',
                        'field' => 'enabled',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonStatusToggle',
                        'visible' => true,
                        'width' => '160',
                        'class' => 'text-center',
                    ],
                    [
                        'index' => 8,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/addons') . '/{id}',
                                'icon' => 'bi-eye',
                                'variant' => 'info',
                                'title' => '查看插件详情',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'addon-show-{id}',
                                    'data-iframe-shell-src' => admin_route('system/addons') . '/{id}',
                                    'data-iframe-shell-title' => '插件详情 - {name}',
                                    'data-iframe-shell-channel' => 'addon',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'exportAddon(\'{id}\', \'{name}\', {enabled})',
                                'icon' => 'bi-download',
                                'variant' => 'success',
                                'title' => '导出插件为zip文件（仅限禁用状态）',
                                'condition' => '!{enabled}'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'configureAddon(\'{id}\')',
                                'icon' => 'bi-gear',
                                'variant' => 'secondary',
                                'title' => '配置插件参数',
                                'condition' => '{enabled}'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteAddon(\'{id}\', \'{name}\', {enabled})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除插件（仅限禁用状态）',
                                'condition' => '!{enabled}'
                            ]
                        ],
                        'visible' => true,
                        'width' => '80',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => $addons ?? [],
                'emptyMessage' => '暂无插件数据',
                'leftButtons' => [
                    [
                        'type' => 'button',
                        'onclick' => 'installAddon()',
                        'text' => '安装插件',
                        'icon' => 'bi-upload',
                        'variant' => 'outline-primary',
                    ]
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@include('components.addon.addon-js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initAddonPage === 'function') {
        window.initAddonPage({
            routes: {
                base: '{{ admin_route('system/addons') }}'
            }
        });
    }
});
</script>
@endpush
