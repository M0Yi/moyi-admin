@extends('admin.layouts.admin')

@section('title', '菜单管理')

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
        <h6 class="mb-1 fw-bold">菜单管理</h6>
        <small class="text-muted">管理系统菜单，支持树形结构</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'menuTable',
                'storageKey' => 'menuTableColumns',
                'ajaxUrl' => admin_route('system/menus'),  // 启用 AJAX 模式
                'showPagination' => false,  // 菜单管理不需要分页功能
                'defaultPageSize' => 500,  // 默认每页显示 500 条数据'
                'columns' => [
                    // ID列
                    [
                        'index' => 0,
                        'label' => 'ID',
                        'field' => 'id',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '50',
                    ],

                    // 菜单名称列（树形结构，使用自定义渲染）
                    [
                        'index' => 1,
                        'label' => '菜单名称',
                        'field' => 'name',
                        'type' => 'custom',
                        'renderFunction' => 'renderMenuName',
                        'visible' => true,
                    ],

                    // 菜单标题列
                    [
                        'index' => 2,
                        'label' => '菜单标题',
                        'field' => 'title',
                        'type' => 'text',
                        'visible' => true,
                    ],

                    // 图标列
                    [
                        'index' => 3,
                        'label' => '图标',
                        'field' => 'icon',
                        'type' => 'icon',
                        'size' => '1.2rem',
                        'visible' => true,
                        'width' => '80',
                    ],

                    // 类型列
                    [
                        'index' => 4,
                        'label' => '类型',
                        'field' => 'type',
                        'type' => 'badge',
                        'badgeMap' => [
                            'menu' => ['text' => '菜单', 'variant' => 'primary'],
                            'link' => ['text' => '外链', 'variant' => 'info'],
                            'group' => ['text' => '分组', 'variant' => 'secondary'],
                            'divider' => ['text' => '分割线', 'variant' => 'secondary'],
                        ],
                        'visible' => true,
                        'width' => '80',
                    ],

                    // 路由路径列
                    [
                        'index' => 5,
                        'label' => '路由路径',
                        'field' => 'path',
                        'type' => 'code',
                        'visible' => true,
                        'width' => '150',
                    ],

                    // 权限标识列
                    [
                        'index' => 6,
                        'label' => '权限标识',
                        'field' => 'permission',
                        'type' => 'code',
                        'visible' => false,
                        'width' => '150',
                    ],

                    // 排序列
                    [
                        'index' => 7,
                        'label' => '排序',
                        'field' => 'sort',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '70',
                    ],

                    // 状态列
                    [
                        'index' => 8,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/menus') . '/{id}/toggle-status\', \'status\')',
                        'visible' => false,  // 默认不显示
                        'width' => '70',
                    ],

                    // 可见性列
                    [
                        'index' => 9,
                        'label' => '可见性',
                        'field' => 'visible',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/menus') . '/{id}/toggle-status\', \'visible\')',
                        'visible' => true,
                        'width' => '70',
                    ],

                    // 徽章列
                    [
                        'index' => 10,
                        'label' => '徽章',
                        'field' => 'badge',
                        'type' => 'custom',
                        'renderFunction' => 'renderMenuBadge',
                        'visible' => false,
                        'width' => '100',
                    ],

                    // 缓存列
                    [
                        'index' => 11,
                        'label' => '缓存',
                        'field' => 'cache',
                        'type' => 'badge',
                        'badgeMap' => [
                            1 => ['text' => '启用', 'variant' => 'success'],
                            0 => ['text' => '禁用', 'variant' => 'secondary'],
                        ],
                        'visible' => false,
                        'width' => '70',
                    ],

                    // 备注列
                    [
                        'index' => 12,
                        'label' => '备注',
                        'field' => 'remark',
                        'type' => 'text',
                        'truncate' => 50,
                        'visible' => false,
                        'width' => '200',
                    ],

                    // 创建时间列
                    [
                        'index' => 13,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => false,
                        'width' => '150',
                    ],

                    // 更新时间列
                    [
                        'index' => 14,
                        'label' => '更新时间',
                        'field' => 'updated_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => false,
                        'width' => '150',
                    ],

                    // 操作列
                    [
                        'index' => 15,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('system/menus') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'menu-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/menus') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑菜单',
                                    'data-iframe-shell-channel' => 'menu',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_menuTable({id})',
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
                'data' => [],  // 初始为空，通过 AJAX 加载
                'emptyMessage' => '暂无菜单数据',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/menus/create'),
                        'text' => '新建菜单',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'menu-create',
                            'data-iframe-shell-src' => admin_route('system/menus/create'),
                            'data-iframe-shell-title' => '新建菜单',
                            'data-iframe-shell-channel' => 'menu',
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
{{-- 引入通用刷新父页面监听器 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
/**
 * ===================================================================
 * 刷新父页面监听器初始化
 * ===================================================================
 *
 * 使用通用监听器组件自动处理 refreshParent 消息：
 * - 监听来自 iframe 的 refreshParent: true 消息
 * - 自动调用 loadData_menuTable() 刷新数据表
 * - 支持频道过滤（可选）
 *
 * 优势：
 * - 无需手动编写消息监听代码
 * - 自动检测并调用对应的刷新函数
 * - 统一的日志输出，便于调试
 *
 * ===================================================================
 */
(function() {
    'use strict';

    // 设置删除路由模板（供组件使用，不需要等待 DOM 就绪）
    window.destroyRouteTemplate_menuTable = '{{ admin_route("system/menus") }}';

    // 初始化刷新父页面监听器
    // 参数1: tableId（数据表格的 ID）
    // 参数2: 配置选项（可选）
    initRefreshParentListener('menuTable', {
        // channel: 'menu-channel',  // 可选：如果设置了频道，只处理该频道的消息
        logPrefix: '[Menu]'         // 可选：日志前缀，用于调试
    });
})();

/**
 * 渲染菜单名称（树形结构）
 */
function renderMenuName(value, column, row) {
    const level = row.level || 0;
    // 仅用一个 "└─" 表示有父级，层级深度主要通过缩进样式体现，避免视觉过于复杂
    const indent = level > 0 ? '└─ ' : '';
    return `<div class="d-flex align-items-center menu-level-${level}">
        ${level > 0 ? `<span class="text-muted me-2">${indent}</span>` : ''}
        <span class="fw-medium">${value || '-'}</span>
    </div>`;
}

/**
 * 渲染菜单徽章
 */
function renderMenuBadge(value, column, row) {
    if (!value) {
        return '<span class="text-muted">-</span>';
    }
    const badgeType = row.badge_type || 'primary';
    return `<span class="badge bg-${badgeType}">${value}</span>`;
}

/**
 * 切换菜单状态
 */
// 状态切换已使用全局的 toggleStatus 函数，无需本地函数
// 删除功能已使用组件标准的 deleteRow_menuTable 函数，无需本地函数
</script>
@endpush
