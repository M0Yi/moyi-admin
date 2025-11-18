@extends('admin.layouts.admin')

@section('title', 'CRUD生成器')

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@section('content')
<div class="container-fluid py-4">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">CRUD 代码生成器</h6>
        <small class="text-muted">管理已生成的 CRUD 配置记录</small>
    </div>

    <!-- CRUD 配置记录列表卡片 -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.action-toolbar', [
                'buttons' => [
                    [
                        'type' => 'link',
                        'href' => admin_route('system/crud-generator/create'),
                        'text' => '新建 CRUD',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary'
                    ]
                ],
                'rightButtons' => [
                    ['icon' => 'bi-arrow-repeat', 'title' => '刷新', 'onclick' => 'window.location.reload()']
                ]
            ])

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="crudTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">ID</th>
                            <th>表名</th>
                            <th width="120">数据库连接</th>
                            <th width="120">模块名称</th>
                            <th width="120">模型名称</th>
                            <th width="80">状态</th>
                            <th width="150">创建时间</th>
                            <th width="150">更新时间</th>
                            <th width="180" class="sticky-column">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($configs as $config)
                        <tr>
                            <td>{{ $config->id }}</td>
                            <td>
                                <code style="color: #6366f1; background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">{{ $config->table_name }}</code>
                            </td>
                            <td>
                                @php
                                    $dbConn = $config->db_connection ?? 'default';
                                @endphp
                                <span class="badge bg-info">
                                    <i class="bi bi-database"></i> {{ $dbConn }}
                                </span>
                            </td>
                            <td>
                                <span class="text-muted small">{{ $config->module_name }}</span>
                            </td>
                            <td>
                                <strong>{{ $config->model_name }}</strong>
                            </td>
                            <td>
                                @if($config->status == \App\Model\Admin\AdminCrudConfig::STATUS_GENERATED)
                                    <span class="badge bg-success">启用</span>
                                @else
                                    <span class="badge bg-secondary">未启用</span>
                                @endif
                            </td>
                            <td>
                                @if($config->created_at)
                                    <small class="text-muted">{{ $config->created_at->format('Y-m-d H:i:s') }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($config->updated_at)
                                    <small class="text-muted">{{ $config->updated_at->format('Y-m-d H:i:s') }}</small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="sticky-column">
                                <div class="d-flex gap-1">
                                    <a href="{{ admin_route('system/crud-generator/config/' . $config->table_name) }}?connection={{ $dbConn }}"
                                       class="btn btn-sm btn-warning btn-action"
                                       title="编辑配置">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger btn-action"
                                            onclick="deleteConfig({{ $config->id }}, '{{ $config->table_name }}')"
                                            title="删除">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                暂无 CRUD 配置记录
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 功能说明 -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-info-circle"></i> 功能说明</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">操作按钮说明：</h6>
                    <ul class="mb-0">
                        <li><strong>编辑：</strong>修改 CRUD 配置，调整字段设置</li>
                        <li><strong>删除：</strong>删除该 CRUD 配置记录</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold mb-2">状态说明：</h6>
                    <ul class="mb-0">
                        <li><span class="badge bg-secondary">未启用</span> - CRUD 配置已保存，但未启用</li>
                        <li><span class="badge bg-success">启用</span> - CRUD 配置已启用，菜单已生成</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        确认删除
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <strong>警告：</strong>删除后将无法恢复！
                    </div>
                    <p class="mb-0">确定要删除配置 <strong id="deleteConfigName"></strong> 吗？</p>
                    <p class="text-muted small mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        删除后，该 CRUD 配置记录将永久移除。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        取消
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash me-1"></i>
                        确认删除
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.common.scripts')

<script>
/**
 * 删除配置
 */
function deleteConfig(id, name) {
    showDeleteModal(id, name, false, 'deleteModal', 'deleteConfigName');
}

// 绑定确认删除按钮事件
document.addEventListener('DOMContentLoaded', function() {
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            // 使用通用的 executeDelete 函数
            executeDelete((id) => adminRoute(`system/crud-generator/${id}`));
        });
    }
});
</script>
@endsection
