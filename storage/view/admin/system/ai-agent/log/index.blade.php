@extends('admin.layouts.admin')

@section('title', 'AI Agent 使用日志')

@section('content')
<div class="page-header">
    <h1>AI Agent 使用日志</h1>
</div>

{{-- 统计卡片 --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3>{{ $statistics['total'] }}</h3>
                <p class="text-muted">总调用次数</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success">{{ $statistics['success'] }}</h3>
                <p class="text-muted">成功次数</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-danger">{{ $statistics['failed'] }}</h3>
                <p class="text-muted">失败次数</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h3>{{ $statistics['total_tokens'] }}</h3>
                <p class="text-muted">消耗 Token</p>
            </div>
        </div>
    </div>
</div>

{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Agent</th>
                <th>类型</th>
                <th>用户</th>
                <th>会话ID</th>
                <th>状态</th>
                <th>Token</th>
                <th>时长(ms)</th>
                <th>时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $log)
            <tr>
                <td>{{ $log->id }}</td>
                <td>{{ $log->agent_name }}</td>
                <td>{{ $log->agent_type }}</td>
                <td>{{ $log->username ?? '-' }}</td>
                <td><small>{{ $log->session_id ?? '-' }}</small></td>
                <td>
                    @if($log->status == 1)
                    <span class="badge badge-success">成功</span>
                    @else
                    <span class="badge badge-danger">失败</span>
                    @endif
                </td>
                <td>{{ $log->tokens ?? 0 }}</td>
                <td>{{ $log->duration ?? 0 }}</td>
                <td>{{ $log->created_at }}</td>
                <td>
                    <a href="{{ admin_route('system/ai-agent-logs') }}/{{ $log->id }}" class="btn btn-sm btn-info">详情</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">暂无数据</td>
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
