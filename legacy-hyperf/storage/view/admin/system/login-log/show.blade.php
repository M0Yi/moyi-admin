@extends('admin.layouts.admin')

@section('title', '登录日志详情')

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
        <h6 class="mb-1 fw-bold">登录日志详情</h6>
        <small class="text-muted">管理员登录记录详情</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table class="table table-bordered">
                <tr><th width="150">日志ID</th><td>{{ $log->id }}</td></tr>
                <tr><th>用户名</th><td>{{ $log->username }}</td></tr>
                <tr><th>所属站点</th><td>{{ $log->site->name ?? '-' }}</td></tr>
                <tr><th>后台入口</th><td>{{ $log->admin_entry_path ?? '-' }}</td></tr>
                <tr><th>IP</th><td>{{ $log->ip }}</td></tr>
                <tr><th>代理链</th><td>@if(!empty($log->ip_list)){{ implode(', ', $log->ip_list) }}@else - @endif</td></tr>
                <tr><th>User Agent</th><td><small class="text-muted">{{ $log->user_agent ?? '-' }}</small></td></tr>
                <tr><th>状态</th><td>{{ $log->status==1 ? '成功' : '失败' }}</td></tr>
                <tr><th>消息</th><td>{{ $log->message ?? '-' }}</td></tr>
                <tr><th>时间</th><td>{{ $log->created_at }}</td></tr>
            </table>
            {{-- 返回列表 按钮已移除，详情页不再显示返回按钮 --}}
        </div>
    </div>
</div>
@endsection


