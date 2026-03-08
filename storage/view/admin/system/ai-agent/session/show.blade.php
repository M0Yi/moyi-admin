@extends('admin.layouts.admin')

@section('title', '会话详情')

@section('content')
<div class="page-header">
    <h1>会话详情</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agent-sessions') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <table class="table table-bordered">
            <tr>
                <th style="width: 150px;">ID</th>
                <td>{{ $session->id }}</td>
            </tr>
            <tr>
                <th>会话ID</th>
                <td><code>{{ $session->session_id }}</code></td>
            </tr>
            <tr>
                <th>用户类型</th>
                <td>{{ $session->user_type }}</td>
            </tr>
            <tr>
                <th>用户名称</th>
                <td>{{ $session->user_name ?? '-' }}</td>
            </tr>
            <tr>
                <th>消息数</th>
                <td>{{ $session->message_count }}</td>
            </tr>
            <tr>
                <th>Token 消耗</th>
                <td>{{ $session->total_tokens }}</td>
            </tr>
            <tr>
                <th>状态</th>
                <td>
                    @if($session->status == 1)
                    <span class="badge badge-success">进行中</span>
                    @else
                    <span class="badge badge-secondary">已结束</span>
                    @endif
                </td>
            </tr>
            <tr>
                <th>创建时间</th>
                <td>{{ $session->created_at }}</td>
            </tr>
            <tr>
                <th>最后消息时间</th>
                <td>{{ $session->last_message_at }}</td>
            </tr>
        </table>
    </div>
</div>

{{-- 会话消息 --}}
<div class="card">
    <div class="card-header">
        <h5>会话消息</h5>
    </div>
    <div class="card-body">
        @if($session->context && count($session->context) > 0)
        <div class="chat-messages">
            @foreach($session->context as $msg)
            <div class="message mb-3">
                <div class="message-header">
                    @if($msg['role'] == 'user')
                    <span class="badge badge-primary">用户</span>
                    @else
                    <span class="badge badge-success">AI</span>
                    @endif
                    <small class="text-muted">{{ date('Y-m-d H:i:s', $msg['timestamp'] ?? time()) }}</small>
                </div>
                <div class="message-content mt-2 p-3 bg-light rounded">
                    {{ $msg['content'] }}
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-muted">暂无消息</p>
        @endif
    </div>
</div>
@endsection
