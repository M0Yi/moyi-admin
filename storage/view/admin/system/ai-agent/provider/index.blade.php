@extends('admin.layouts.admin')

@section('title', 'AI Provider 管理')

@section('content')
<div class="page-header">
    <h1>AI Provider 管理</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-providers') }}/create" class="btn btn-primary">
            <i class="bi bi-plus"></i> 新增 Provider
        </a>
    </div>
</div>

{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>标识</th>
                <th>驱动</th>
                <th>API URL</th>
                <th>默认</th>
                <th>状态</th>
                <th>排序</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $provider)
            <tr>
                <td>{{ $provider->id }}</td>
                <td>{{ $provider->name }}</td>
                <td><code>{{ $provider->slug }}</code></td>
                <td><code>{{ $provider->driver }}</code></td>
                <td><small>{{ $provider->base_url }}</small></td>
                <td>
                    @if($provider->is_default == 1)
                    <span class="badge badge-warning">默认</span>
                    @else
                    <a href="{{ admin_route('system/ai-providers') }}/{{ $provider->id }}/set-default" class="btn btn-sm btn-outline-secondary">设为默认</a>
                    @endif
                </td>
                <td>
                    @if($provider->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
                <td>{{ $provider->sort }}</td>
                <td>
                    <a href="{{ admin_route('system/ai-providers') }}/{{ $provider->id }}" class="btn btn-sm btn-info">查看</a>
                    <a href="{{ admin_route('system/ai-providers') }}/{{ $provider->id }}/edit" class="btn btn-sm btn-primary">编辑</a>
                    <button class="btn btn-sm btn-danger" onclick="deleteProvider({{ $provider->id }})">删除</button>
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

@push('scripts')
<script>
function deleteProvider(id) {
    if (!confirm('确定要删除该 Provider 吗？')) {
        return;
    }
    fetch(`{{ admin_route('system/ai-providers') }}/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.code === 200) {
            location.reload();
        } else {
            alert(data.msg || '删除失败');
        }
    });
}
</script>
@endpush
