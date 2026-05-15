@extends('layouts.admin')

@section('title', 'AI 知识库')

@section('content')
<div class="page-header">
    <h1>AI 知识库</h1>
    <div class="actions">
        <a href="{{ admin_route('system/ai-knowledge') }}/create" class="btn btn-primary">
            <i class="bi bi-plus"></i> 新增文档
        </a>
    </div>
</div>

{{-- 数据表格 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>关键词</th>
                <th>状态</th>
                <th>排序</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($list as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->title }}</td>
                <td>
                    @if($item->keywords)
                    @foreach($item->keywords as $kw)
                    <span class="badge badge-info">{{ $kw }}</span>
                    @endforeach
                    @else
                    -
                    @endif
                </td>
                <td>
                    @if($item->status == 1)
                    <span class="badge badge-success">启用</span>
                    @else
                    <span class="badge badge-danger">禁用</span>
                    @endif
                </td>
                <td>{{ $item->sort }}</td>
                <td>{{ $item->created_at }}</td>
                <td>
                    <a href="{{ admin_route('system/ai-knowledge') }}/{{ $item->id }}" class="btn btn-sm btn-info">查看</a>
                    <a href="{{ admin_route('system/ai-knowledge') }}/{{ $item->id }}/edit" class="btn btn-sm btn-primary">编辑</a>
                    <button class="btn btn-sm btn-danger" onclick="deleteKnowledge({{ $item->id }})">删除</button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">暂无数据</td>
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
function deleteKnowledge(id) {
    if (!confirm('确定要删除该文档吗？')) {
        return;
    }
    fetch(`{{ admin_route('system/ai-knowledge') }}/${id}`, {
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
