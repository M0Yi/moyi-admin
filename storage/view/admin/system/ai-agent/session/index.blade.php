@extends('layouts.admin')

@section('title', 'AI 客服会话')

@section('content')
<div class="page-header">
    <h1>AI 客服会话</h1>
</div>

{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>会话ID</th>
                <th>用户类型</th>
                <th>用户名称</th>
                <th>消息数</th>
                <th>Token</th>
                <th>状态</th>
                <th>最后消息</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $session)
            <tr>
                <td>{{ $session->id }}</td>
                <td><small>{{ $session->session_id }}</small></td>
                <td>{{ $session->user_type }}</td>
                <td>{{ $session->user_name ?? '-' }}</td>
                <td>{{ $session->message_count }}</td>
                <td>{{ $session->total_tokens }}</td>
                <td>
                    @if($session->status == 1)
                    <span class="badge badge-success">进行中</span>
                    @else
                    <span class="badge badge-secondary">已结束</span>
                    @endif
                </td>
                <td>{{ $session->last_message_at }}</td>
                <td>
                    <a href="{{ admin_route('system/ai-agent-sessions') }}/{{ $session->id }}" class="btn btn-sm btn-info">查看</a>
                    @if($session->status == 1)
                    <button class="btn btn-sm btn-warning" onclick="endSession({{ $session->id }})">结束</button>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center">暂无数据</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 分页 --}}
@if($total > $page_size)
<div class="pagination-wrapper">
    {{ $list->appends(request()->query())->links() }}
</div>
@endif
@endsection
