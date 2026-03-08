@extends('admin.layouts.admin')

@section('title', 'AI Agent 管理')

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
        <h6 class="mb-1 fw-bold">AI Agent 管理</h6>
        <small class="text-muted">管理 AI Agent 配置和状态</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'aiAgentTable',
                'storageKey' => 'aiAgentTableColumns',
                'ajaxUrl' => admin_route('system/ai-agents'),
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
                        'label' => '名称',
                        'field' => 'name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '标识',
                        'field' => 'slug',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 3,
                        'label' => '类型',
                        'field' => 'type',
                        'type' => 'badge',
                        'badgeMap' => [
                            'audit' => ['text' => '审核', 'variant' => 'info'],
                            'service' => ['text' => '客服', 'variant' => 'success'],
                            'assistant' => ['text' => '助手', 'variant' => 'primary'],
                        ],
                        'visible' => true,
                    ],
                    [
                        'index' => 4,
                        'label' => '类名',
                        'field' => 'class',
                        'type' => 'text',
                        'visible' => false,
                    ],
                    [
                        'index' => 5,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => "toggleStatus({id}, this, '" . admin_route('system/ai-agents') . "/{id}/toggle-status', 'status')",
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 6,
                        'label' => '默认',
                        'field' => 'is_default',
                        'type' => 'badge',
                        'badgeMap' => [
                            '1' => ['text' => '默认', 'variant' => 'warning'],
                            '0' => ['text' => '普通', 'variant' => 'secondary'],
                        ],
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 7,
                        'label' => '排序',
                        'field' => 'sort',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '60',
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
                                'href' => admin_route('system/ai-agents') . '/create',
                                'icon' => 'bi-plus',
                                'variant' => 'primary',
                                'title' => '新增',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'ai-agent-create',
                                    'data-iframe-shell-src' => admin_route('system/ai-agents') . '/create',
                                    'data-iframe-shell-title' => '新增 AI Agent',
                                    'data-iframe-shell-channel' => 'ai-agent',
                                ],
                            ],
                            [
                                'type' => 'link',
                                'href' => admin_route('system/ai-agents') . '/{id}/edit',
                                'icon' => 'bi-pencil',
                                'variant' => 'warning',
                                'title' => '编辑',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'ai-agent-edit-{id}',
                                    'data-iframe-shell-src' => admin_route('system/ai-agents') . '/{id}/edit',
                                    'data-iframe-shell-title' => '编辑 AI Agent',
                                    'data-iframe-shell-channel' => 'ai-agent',
                                ],
                            ],
                            [
                                'type' => 'button',
                                'onclick' => "deleteRow_aiAgentTable({id})",
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除',
                            ],
                        ],
                    ],
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
<script>
function deleteRow_aiAgentTable(id) {
    if (confirm('确定要删除这条记录吗？')) {
        fetch(`{{ admin_route('system/ai-agents') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                window.location.reload();
            } else {
                alert(data.msg || '删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除失败');
        });
    }
}
</script>
@endpush
