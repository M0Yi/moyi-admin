@extends('admin.layouts.admin')

@section('title', '拦截日志详情')

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
        <h6 class="mb-1 fw-bold">拦截日志详情</h6>
        <small class="text-muted">查看拦截日志的详细信息</small>
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
                            <th>拦截类型</th>
                            <td>
                                @php
                                    $typeLabels = [
                                        '404' => '页面不存在',
                                        'invalid_path' => '非法路径',
                                        'unauthorized' => '未授权访问'
                                    ];
                                    $typeColors = [
                                        '404' => 'warning',
                                        'invalid_path' => 'danger',
                                        'unauthorized' => 'danger'
                                    ];
                                    $typeLabel = $typeLabels[$log->intercept_type] ?? $log->intercept_type;
                                    $typeColor = $typeColors[$log->intercept_type] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $typeColor }}">{{ $typeLabel }}</span>
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
                                        'HEAD' => 'secondary',
                                        'OPTIONS' => 'info'
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
                                <span class="badge bg-{{ $statusColor }}">{{ $log->status_code }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>拦截原因</th>
                            <td>{{ $log->reason ?? '-' }}</td>
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
                            <th>拦截时间</th>
                            <td>{{ $log->created_at }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">后台入口</th>
                            <td>{{ $log->admin_entry_path ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Agent</th>
                            <td>
                                <small class="text-muted">{{ $log->user_agent ?? '-' }}</small>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($log->params)
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
            @endif

            <div class="row mt-3">
                <div class="col-12">
                    {{-- 详情页不再显示返回按钮 --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@endpush
