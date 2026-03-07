@extends('admin.layouts.admin')

@section('title', '日志详情')

@section('content')
<div class="page-header">
    <h1>日志详情</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agent-logs') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">ID</th>
                <td>{{ $log->id }}</td>
            </tr>
            <tr>
                <th>Agent</th>
                <td>{{ $log->agent_name }} ({{ $log->agent_type }})</td>
            </tr>
            <tr>
                <th>用户</th>
                <td>{{ $log->username ?? '-' }} (ID: {{ $log->user_id ?? '-' }})</td>
            </tr>
            <tr>
                <th>会话ID</th>
                <td>{{ $log->session_id ?? '-' }}</td>
            </tr>
            <tr>
                <th>状态</th>
                <td>
                    @if($log->status == 1)
                    <span class="badge badge-success">成功</span>
                    @else
                    <span class="badge badge-danger">失败</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>Token 消耗</th>
                <td>{{ $log->tokens ?? 0 }}</td>
            </tr>
            <tr>
                <th>执行时长</th>
                <td>{{ $log->duration ?? 0 }} ms</td>
            </tr>
            <tr>
                <th>IP</th>
                <td>{{ $log->ip ?? '-' }}</td>
            </tr>
            <tr>
                <th>时间</th>
                <td>{{ $log->created_at }}</td>
            </tr>
            @if($log->error_message)
            <tr>
                <th>错误信息</th>
                <td class="text-danger">{{ $log->error_message }}</td>
            </tr>
            @endif
        </table>

        @if($log->prompt)
        <h5>输入内容</h5>
        <pre class="bg-light p-3">{{ $log->prompt }}</pre>
        @endif

        @if($log->content)
        <h5>待处理内容</h5>
        <pre class="bg-light p-3">{{ $log->content }}</pre>
        @endif

        @if($log->result)
        <h5>处理结果</h5>
        <pre class="bg-light p-3">{{ json_encode($log->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        @endif
    </div>
</div>
@endsection
