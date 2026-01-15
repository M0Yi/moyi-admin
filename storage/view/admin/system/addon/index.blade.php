@extends('admin.layouts.admin')

@section('title', '插件管理')

@php
    // 新的统一渲染方案 - 使用 visible 字段控制按钮显示：
    // 主要依赖安装状态和版本状态，不区分来源（store/local）
    // 所有按钮显示控制都使用 visible 字段，统一调用方式：
    //    'visible' => 'function(value, row, column) { return 条件逻辑; }'
    //
    // 按钮显示规则：
    // - 安装插件：未安装时显示（不管来源）
    // - 升级插件：已安装 + 可升级时显示
    // - 启用插件：已安装 + 未启用时显示
    // - 禁用插件：已安装 + 已启用时显示
    // - 配置插件：已安装 + 已启用时显示
    // - 导出插件：已安装 + 未启用时显示（只能在禁用状态下导出）
    // - 删除插件：已安装 + 未启用时显示（只能在禁用状态下删除）

    $addonSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($addonSearchConfig['search_fields'] ?? []);
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
        <h6 class="mb-1 fw-bold">插件管理</h6>
        <small class="text-muted">管理系统插件，自动扫描 addons 目录。启用插件将自动完成安装和路由加载，禁用插件将清理相关文件。</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @php
                // 从已有的 $addons 中收集分类选项（用于下拉）
                $categoryOptions = [];
                if (!empty($addons) && is_array($addons)) {
                    foreach ($addons as $ad) {
                        $c = $ad['category'] ?? null;
                        if ($c) {
                            $categoryOptions[(string)$c] = ['value' => (string)$c, 'label' => (string)$c];
                        }
                    }
                }

                // 插件管理页面的筛选与搜索配置
                $addonSearchConfig = [
                    'search_fields' => ['name', 'version', 'category', 'enabled', 'source'],
                    'fields' => [
                        ['name' => 'name', 'label' => '插件名称', 'type' => 'string', 'placeholder' => '请输入插件名称', 'col' => 'col-12 col-md-3'],
                        ['name' => 'version', 'label' => '版本', 'type' => 'string', 'placeholder' => '请输入版本号', 'col' => 'col-12 col-md-3'],
                        ['name' => 'category', 'label' => '分类', 'type' => 'select', 'options' => array_values($categoryOptions), 'placeholder' => '请选择分类', 'col' => 'col-12 col-md-3'],
                        ['name' => 'source', 'label' => '来源', 'type' => 'select', 'options' => [
                            ['value' => '', 'label' => '全部'],
                            ['value' => 'local', 'label' => '本地插件'],
                            ['value' => 'store', 'label' => '应用商城'],
                        ], 'placeholder' => '请选择来源', 'col' => 'col-12 col-md-3'],
                        ['name' => 'enabled', 'label' => '启用状态', 'type' => 'select', 'options' => [
                            ['value' => '', 'label' => '全部'],
                            ['value' => '1', 'label' => '已启用'],
                            ['value' => '0', 'label' => '已禁用'],
                        ], 'col' => 'col-12 col-md-3'],
                    ],
                ];

                // 计算是否有搜索配置（用于页面脚本和渲染器初始化）
                $hasSearchConfig = !empty($addonSearchConfig['search_fields'] ?? []);
            @endphp
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'addonTable',
                'storageKey' => 'addonTableColumns',
                'ajaxUrl' => admin_route('system/addons'),
                'searchFormId' => 'searchForm_addonTable',
                'searchPanelId' => 'searchPanel_addonTable',
                'searchConfig' => $addonSearchConfig,
                'showSearch' => true,
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
                        'visible' => false,
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
                        'visible' => false,
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
                        'label' => '来源',
                        'field' => 'source',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonSource',
                        'visible' => true,
                        'width' => '100',
                        'class' => 'text-center',
                    ],
                    [
                        'index' => 8,
                        'label' => '安装状态',
                        'field' => 'installed',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonInstallStatus',
                        'visible' => true,
                        'width' => '120',
                        'class' => 'text-center',
                    ],
                    [
                        'index' => 9,
                        'label' => '状态',
                        'field' => 'enabled',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonStatusToggle',
                        'visible' => 'function(value, row, column) { return row.installed === true; }',
                        'width' => '120',
                        'class' => 'text-center',
                    ],
                    [
                        'index' => 10,
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
                            // 插件安装相关操作
                            [
                                'type' => 'button',
                                'onclick' => 'installStoreAddon(\'{id}\', \'{name}\')',
                                'icon' => 'bi-box-arrow-down',
                                'variant' => 'success',
                                'title' => '安装插件',
                                // 使用 visible 字段控制显示：未安装时显示
                                'visible' => 'function(value, row, column) { return !row.installed; }'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'upgradeStoreAddon(\'{id}\', \'{name}\', \'{current_version}\', \'{version}\')',
                                'icon' => 'bi-arrow-up-circle',
                                'variant' => 'warning',
                                'title' => '升级插件到最新版本',
                                // 使用 visible 字段控制显示：已安装且可升级时显示
                                'visible' => 'function(value, row, column) { return row.installed === true && row.can_upgrade === true; }'
                            ],
                            // 插件管理相关操作
                            [
                                'type' => 'button',
                                'onclick' => 'enableAddon(\'{id}\')',
                                'icon' => 'bi-toggle-off',
                                'variant' => 'success',
                                'title' => '启用插件',
                                // 使用 visible 字段控制显示：已安装且未启用时显示
                                'visible' => 'function(value, row, column) { return row.installed === true && !row.enabled; }'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'disableAddon(\'{id}\')',
                                'icon' => 'bi-toggle-on',
                                'variant' => 'warning',
                                'title' => '禁用插件',
                                // 使用 visible 字段控制显示：已安装且已启用时显示
                                'visible' => 'function(value, row, column) { return row.installed === true && row.enabled === true; }'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'configureAddon(\'{id}\')',
                                'icon' => 'bi-gear',
                                'variant' => 'primary',
                                'title' => '配置插件参数',
                                // 使用 visible 字段控制显示：已安装且已启用时显示
                                'visible' => 'function(value, row, column) { return row.installed === true && row.enabled === true; }'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'exportAddon(\'{id}\', \'{name}\', \'{enabled}\', \'{version}\')',
                                'icon' => 'bi-box-arrow-up-right',
                                'variant' => 'primary',
                                'title' => '导出插件为zip文件',
                                // 使用 visible 字段控制显示：已安装且未启用时显示（只能在禁用状态下导出）
                                'visible' => 'function(value, row, column) { return row.installed === true && row.enabled === false; }'
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteAddon(\'{id}\', \'{name}\', \'{enabled}\')',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除插件',
                                // 使用 visible 字段控制显示：已安装且未启用时显示（只能在禁用状态下删除）
                                'visible' => 'function(value, row, column) { return row.installed === true && row.enabled === false; }'
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
                        'text' => '本地安装',
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
@if ($hasSearchConfig)
    @include('components.admin-script', ['path' => '/js/components/search-form-renderer.js'])
@endif
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

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($addonSearchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_addonTable',
                panelId: 'searchPanel_addonTable',
                tableId: 'addonTable'
            });

            window['_searchFormRenderer_addonTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_addonTable = window.createSearchFormResetFunction('addonTable');
            } else {
                window.resetSearchForm_addonTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[AddonPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
