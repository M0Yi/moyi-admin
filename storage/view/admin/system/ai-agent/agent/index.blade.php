@extends('layouts.admin')

@section('title', 'AI Agent 管理')

@section('content')
<div class="page-header">
    <h1>AI Agent 管理</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-agents') }}/create" class="btn btn-primary">
            <i class="bi bi-plus"></i> 新增 Agent
        </a>
    </div>
</div>

{{-- 搜索表单 --}}
<div class="search-box">
    <form action="{{ admin_route('system/ai-agents') }}" method="GET">
        <div class="row">
            <div class="col-md-3">
                <select name="type" class="form-control">
                    <option value="">全部类型</option>
                    @foreach($types as $key => $label)
                    <option value="{{ $key }}" {{ $type == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">全部状态</option>
                    @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ $status == (string)$key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" class="form-control" placeholder="搜索名称/标识" value="{{ $keyword ?? '' }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="{{ admin_route('system/ai-agents') }}" class="btn btn-default">重置</a>
            </div>
        </div>
    </form>
</div>

{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>标识</th>
                <th>类型</th>
                <th>类名</th>
                <th>状态</th>
                <th>默认</th>
                <th>排序</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $agent)
            <tr>
                <td>{{ $agent->id }}</td>
                <td>{{ $agent->name }}</td>
                <td><code>{{ $agent->slug }}</code></td>
                <td>
                    @if(isset($types[$agent->type]))
                    <span class="badge badge-info">{{ $types[$agent->type] }}</span>
                    @else
                    {{ $agent->type }}
                    @endif
                </td>
                <td><small>{{ $agent->class }}</small></td>
                <td>
                    @if($agent->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
                <td>
                    @if($agent->is_default == 1)
                    <span class="badge badge-warning">默认</span>
                    @else
                    <a href="{{ admin_route('system/ai-agents') }}/{{ $agent->id }}/set-default" class="btn btn-sm btn-outline-secondary">设为默认</a>
                    @endif
                </td>
                <td>{{ $agent->sort }}</td>
                <td>{{ $agent->created_at }}</td>
                <td>
                    <a href="{{ admin_route('system/ai-agents') }}/{{ $agent->id }}" class="btn btn-sm btn-info">查看</a>
                    <a href="{{ admin_route('system/ai-agents') }}/{{ $agent->id }}/edit" class="btn btn-sm btn-primary">编辑</a>
                    <button class="btn btn-sm btn-danger" onclick="deleteAgent({{ $agent->id }})">删除</button>
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

@push('scripts')
<script>
function deleteAgent(id) {
    if (!confirm('确定要删除该 Agent 吗？')) {
        return;
    }

    fetch(`{{ admin_route('system/ai-agents') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.code === 200) {
            alert('删除成功');
            location.reload();
        } else {
            alert(data.msg || '删除失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('删除失败');
    });
}
</script>
@endpush
