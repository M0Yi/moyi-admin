@extends('admin.layouts.admin')

@section('title', '远程数据库')

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
        <h6 class="mb-1 fw-bold">远程数据库</h6>
        <small class="text-muted">管理远程数据库连接配置</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'databaseConnectionTable',
                'storageKey' => 'databaseConnectionTableColumns',
                'ajaxUrl' => admin_route('system/database-connections'),
                'searchFormId' => 'searchForm_databaseConnectionTable',
                'searchPanelId' => 'searchPanel_databaseConnectionTable',
                'searchConfig' => $searchConfig,
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
                        'label' => '连接名称',
                        'field' => 'name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '驱动类型',
                        'field' => 'driver',
                        'type' => 'custom',
                        'renderFunction' => 'renderDriver',
                        'visible' => true,
                    ],
                    [
                        'index' => 3,
                        'label' => '主机地址',
                        'field' => 'host',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 4,
                        'label' => '端口',
                        'field' => 'port',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 5,
                        'label' => '数据库名',
                        'field' => 'database',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 6,
                        'label' => '用户名',
                        'field' => 'username',
                        'type' => 'text',
                        'visible' => false,
                    ],
                    [
                        'index' => 7,
                        'label' => '描述',
                        'field' => 'description',
                        'type' => 'text',
                        'visible' => false,
                    ],
                    [
                        'index' => 8,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('system/database-connections') . '/{id}/toggle-status\', \'status\')',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 9,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 10,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'button',
                                'onclick' => 'testConnection({id})',
                                'icon' => 'bi-plug',
                                'variant' => 'info',
                                'title' => '测试连接',
                                'attributes' => [
                                    'data-bs-toggle' => 'modal',
                                    'data-bs-target' => '#testConnectionModal'
                                ]
                            ],
                            [
                                'type' => 'link',
                                'href' => admin_route('system/database-connections') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'database-connection-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/database-connections') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑数据库连接',
                                    'data-iframe-shell-channel' => 'database-connection',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_databaseConnectionTable({id})',
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
                'emptyMessage' => '暂无数据库连接配置',
                'leftButtons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/database-connections/create'),
                        'text' => '新建连接',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'database-connection-create',
                            'data-iframe-shell-src' => admin_route('system/database-connections/create'),
                            'data-iframe-shell-title' => '新建数据库连接',
                            'data-iframe-shell-channel' => 'database-connection',
                            'data-iframe-shell-hide-actions' => 'true'
                        ]
                    ]
                ],
            ])
        </div>
    </div>
</div>

<!-- 测试连接模态框 -->
<div class="modal fade" id="testConnectionModal" tabindex="-1" aria-labelledby="testConnectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="testConnectionModalLabel">测试数据库连接</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="testPassword" class="form-label">密码（可选）</label>
                    <input type="password" class="form-control" id="testPassword" placeholder="留空则使用已保存的密码">
                    <small class="text-muted">留空则使用已保存的密码进行测试</small>
                </div>
                <div id="testResult" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="doTestConnection()">测试连接</button>
            </div>
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
let currentTestConnectionId = null;

// 渲染驱动类型
function renderDriver(value, row) {
    const driverMap = {
        'mysql': 'MySQL',
        'pgsql': 'PostgreSQL'
    };
    return driverMap[value] || value;
}

// 打开测试连接模态框
function testConnection(id) {
    currentTestConnectionId = id;
    document.getElementById('testPassword').value = '';
    document.getElementById('testResult').classList.add('d-none');
    document.getElementById('testResult').innerHTML = '';
}

// 执行测试连接
function doTestConnection() {
    if (!currentTestConnectionId) {
        alert('连接ID不存在');
        return;
    }

    const password = document.getElementById('testPassword').value;
    // 密码可选，留空则使用已保存的密码

    const resultDiv = document.getElementById('testResult');
    resultDiv.classList.remove('d-none');
    resultDiv.innerHTML = '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm" role="status"></div><span>测试中...</span></div>';

    fetch(`{{ admin_route('system/database-connections') }}/${currentTestConnectionId}/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ password: password })
    })
    .then(response => response.json())
    .then(data => {
        if (data.code === 200) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> ${data.message || '连接成功'}
                    ${data.data?.version ? '<br><small>数据库版本：' + data.data.version + '</small>' : ''}
                    ${data.data?.database ? '<br><small>数据库：' + data.data.database + '</small>' : ''}
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-x-circle"></i> ${data.message || data.msg || '连接失败'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-x-circle"></i> 测试失败：${error.message}
            </div>
        `;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // 初始化表格删除功能
    window.deleteRow_databaseConnectionTable = function(id) {
        if (!confirm('确定要删除该数据库连接配置吗？')) {
            return;
        }

        fetch(`{{ admin_route('system/database-connections') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert('删除成功');
                if (window.databaseConnectionTable && typeof window.databaseConnectionTable.reload === 'function') {
                    window.databaseConnectionTable.reload();
                } else {
                    location.reload();
                }
            } else {
                alert(data.message || data.msg || '删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除失败');
        });
    };
});
</script>
@if ($hasSearchConfig)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const config = @json($searchConfig);
    if (!config || !config.search_fields || !config.search_fields.length) {
        return;
    }
    if (typeof window.SearchFormRenderer !== 'function') {
        console.warn('[DatabaseConnectionPage] SearchFormRenderer 未加载');
        return;
    }

    const renderer = new window.SearchFormRenderer({
        config,
        formId: 'searchForm_databaseConnectionTable',
        panelId: 'searchPanel_databaseConnectionTable',
        tableId: 'databaseConnectionTable'
    });

    window['_searchFormRenderer_databaseConnectionTable'] = renderer;
    if (typeof window.createSearchFormResetFunction === 'function') {
        window.resetSearchForm_databaseConnectionTable = window.createSearchFormResetFunction('databaseConnectionTable');
    } else {
        window.resetSearchForm_databaseConnectionTable = function () {
            if (renderer && typeof renderer.reset === 'function') {
                renderer.reset();
            }
        };
    }
});
</script>
@endif
@endpush

