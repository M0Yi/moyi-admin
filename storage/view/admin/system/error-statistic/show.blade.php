@extends('admin.layouts.admin')

@section('title', '错误统计详情')

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
        <h6 class="mb-1 fw-bold">错误统计详情</h6>
        <small class="text-muted">查看错误统计的详细信息</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">记录ID</th>
                            <td>{{ $errorStat->id }}</td>
                        </tr>
                        <tr>
                            <th>异常类</th>
                            <td><code>{{ $errorStat->exception_class }}</code></td>
                        </tr>
                        <tr>
                            <th>错误消息</th>
                            <td>{{ $errorStat->error_message }}</td>
                        </tr>
                        <tr>
                            <th>错误文件</th>
                            <td><code>{{ $errorStat->error_file }}:{{ $errorStat->error_line }}</code></td>
                        </tr>
                        <tr>
                            <th>错误等级</th>
                            <td>
                                @php
                                    $levelColors = [
                                        'error' => 'danger',
                                        'warning' => 'warning',
                                        'notice' => 'info',
                                    ];
                                    $levelColor = $levelColors[$errorStat->error_level] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $levelColor }}">{{ $errorStat->error_level }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>状态码</th>
                            <td>
                                @php
                                    $statusColor = 'secondary';
                                    if ($errorStat->status_code >= 200 && $errorStat->status_code < 300) {
                                        $statusColor = 'success';
                                    } elseif ($errorStat->status_code >= 300 && $errorStat->status_code < 400) {
                                        $statusColor = 'info';
                                    } elseif ($errorStat->status_code >= 400 && $errorStat->status_code < 500) {
                                        $statusColor = 'warning';
                                    } elseif ($errorStat->status_code >= 500) {
                                        $statusColor = 'danger';
                                    }
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ $errorStat->status_code ?? '-' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>请求方法</th>
                            <td>
                                @php
                                    $methodColors = [
                                        'GET' => 'success',
                                        'POST' => 'primary',
                                        'PUT' => 'warning',
                                        'DELETE' => 'danger',
                                        'PATCH' => 'info',
                                    ];
                                    $methodColor = $methodColors[$errorStat->request_method] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $methodColor }}">{{ $errorStat->request_method ?? '-' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>请求路径</th>
                            <td><code>{{ $errorStat->request_path ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <th>IP地址</th>
                            <td>{{ $errorStat->request_ip ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>用户名</th>
                            <td>{{ $errorStat->username ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>发生次数</th>
                            <td>
                                @if ($errorStat->occurrence_count > 100)
                                    <span class="badge bg-danger">{{ $errorStat->occurrence_count }}</span>
                                @elseif ($errorStat->occurrence_count > 10)
                                    <span class="badge bg-warning">{{ $errorStat->occurrence_count }}</span>
                                @else
                                    <span class="badge bg-info">{{ $errorStat->occurrence_count }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>状态</th>
                            <td>
                                @switch($errorStat->status)
                                    @case(0)
                                        <span class="badge bg-danger">未处理</span>
                                        @break
                                    @case(1)
                                        <span class="badge bg-warning">处理中</span>
                                        @break
                                    @case(2)
                                        <span class="badge bg-success">已解决</span>
                                        @if ($errorStat->resolved_at)
                                            <br><small class="text-muted">解决时间：{{ $errorStat->resolved_at }}</small>
                                        @endif
                                        @break
                                    @default
                                        <span class="badge bg-secondary">未知</span>
                                @endswitch
                            </td>
                        </tr>
                        @if (is_super_admin())
                        <tr>
                            <th>所属站点</th>
                            <td>{{ $errorStat->site_id ? '站点 ' . $errorStat->site_id : '无' }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th>首次发生</th>
                            <td>{{ $errorStat->first_occurred_at }}</td>
                        </tr>
                        <tr>
                            <th>最后发生</th>
                            <td>{{ $errorStat->last_occurred_at }}</td>
                        </tr>
                        <tr>
                            <th>创建时间</th>
                            <td>{{ $errorStat->created_at }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">User Agent</th>
                            <td>
                                <small class="text-muted">{{ $errorStat->user_agent ?? '-' }}</small>
                            </td>
                        </tr>
                        <tr>
                            <th>请求ID</th>
                            <td><code>{{ $errorStat->request_id ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <th>错误代码</th>
                            <td><code>{{ $errorStat->error_code ?? '-' }}</code></td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($errorStat->request_query)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">查询参数</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($errorStat->request_query, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($errorStat->request_body)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">请求体</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($errorStat->request_body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($errorStat->context)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">上下文信息</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($errorStat->context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($errorStat->error_trace)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">错误堆栈</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 400px; overflow-y: auto;"><code>{{ $errorStat->error_trace }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            @if ($errorStat->request_headers)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">请求头</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 200px; overflow-y: auto;"><code>{{ json_encode($errorStat->request_headers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="row mt-3">
                <div class="col-12">
                    {{-- 操作按钮 --}}
                    @if ($errorStat->status < 2)
                        <button type="button" class="btn btn-success" onclick="resolveError()">
                            <i class="bi bi-check-circle"></i> 标记为已解决
                        </button>
                    @endif
                    {{-- 返回列表 按钮已移除，详情页不再显示返回按钮 --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
function resolveError() {
    if (!confirm('确定要将此错误标记为已解决吗？')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(`{{ admin_route("system/error-statistics") }}/{{ $errorStat->id }}/resolve`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.code === 200) {
            alert(data.msg || '标记成功');
            // 刷新父页面
            if (window.parent && window.parent !== window) {
                window.parent.location.reload();
            } else {
                window.location.reload();
            }
        } else {
            alert(data.msg || data.message || '标记失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('标记失败');
    });
}
</script>
@endpush
