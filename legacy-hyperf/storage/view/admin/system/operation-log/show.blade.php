@extends('admin.layouts.admin')

@section('title', '操作日志详情')

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
        <h6 class="mb-1 fw-bold">操作日志详情</h6>
        <small class="text-muted">查看操作日志的详细信息</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">日志ID</th>
                            <td>{{ $log->id }}</td>
                        </tr>
                        <tr>
                            <th>用户名</th>
                            <td>{{ $log->username }}</td>
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
                                    $methodColor = $methodColors[$log->method] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $methodColor }}">{{ $log->method }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>请求路径</th>
                            <td><code>{{ $log->path }}</code></td>
                        </tr>
                        <tr>
                            <th>IP地址</th>
                            <td>{{ $log->ip }}</td>
                        </tr>
                        <tr>
                            <th>代理链</th>
                            <td>
                                @php
                                    $ipList = $log->ip_list ?? [];
                                @endphp
                                @if (!empty($ipList) && is_array($ipList))
                                    <small class="text-muted">{{ implode(', ', $ipList) }}</small>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>状态码</th>
                            <td>
                                @php
                                    $statusColor = 'secondary';
                                    if ($log->status_code >= 200 && $log->status_code < 300) {
                                        $statusColor = 'success';
                                    } elseif ($log->status_code >= 300 && $log->status_code < 400) {
                                        $statusColor = 'info';
                                    } elseif ($log->status_code >= 400 && $log->status_code < 500) {
                                        $statusColor = 'warning';
                                    } elseif ($log->status_code >= 500) {
                                        $statusColor = 'danger';
                                    }
                                @endphp
                                <span class="badge bg-{{ $statusColor }}">{{ $log->status_code ?? '-' }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>执行时长</th>
                            <td>
                                @if ($log->duration)
                                    @if ($log->duration < 100)
                                        <span class="text-success">{{ $log->duration }}ms</span>
                                    @elseif ($log->duration < 500)
                                        <span class="text-warning">{{ $log->duration }}ms</span>
                                    @else
                                        <span class="text-danger">{{ $log->duration }}ms</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @if (is_super_admin() && $log->site)
                        <tr>
                            <th>所属站点</th>
                            <td>{{ $log->site->name }}</td>
                        </tr>
                        @endif
                        <tr>
                            <th>操作时间</th>
                            <td>{{ $log->created_at }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">User Agent</th>
                            <td>
                                <small class="text-muted">{{ $log->user_agent ?? '-' }}</small>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">请求参数</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 300px; overflow-y: auto;"><code>{{ json_encode($log->params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            @if ($log->response)
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="mb-2">响应数据</h6>
                    <div class="card bg-light">
                        <div class="card-body">
                            <pre class="mb-0" style="max-height: 300px; overflow-y: auto;"><code>{{ json_encode($log->response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="row mt-3">
                <div class="col-12">
                    {{-- 返回列表 按钮已移除，详情页不再显示返回按钮 --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@endpush

