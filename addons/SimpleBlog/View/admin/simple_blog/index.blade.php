@extends('admin.layouts.admin')

@section('title', '博客文章管理')

@push('styles')
<style>
    .stats-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stats-number {
        font-size: 24px;
        font-weight: bold;
        color: var(--primary-color);
    }
    .table-responsive {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1>博客文章管理</h1>
    <div class="actions">
        <a href="/admin/simple_blog/create" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> 新建文章
        </a>
    </div>
</div>

{{-- 统计信息 --}}
<div class="row">
    <div class="col-md-3">
        <div class="stats-card">
            <h5>总文章数</h5>
            <div class="stats-number">{{ $stats['total_posts'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h5>已发布</h5>
            <div class="stats-number">{{ $stats['published_posts'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h5>草稿</h5>
            <div class="stats-number">{{ $stats['draft_posts'] }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stats-card">
            <h5>总浏览量</h5>
            <div class="stats-number">{{ $stats['total_views'] }}</div>
        </div>
    </div>
</div>

{{-- 搜索表单 --}}
<div class="search-box" style="background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <form action="/admin/simple_blog" method="GET">
        <div class="row">
            <div class="col-md-3">
                <input type="text" name="keyword" class="form-control"
                       placeholder="搜索标题或内容" value="{{ $params['keyword'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">全部状态</option>
                    <option value="published" {{ isset($params['status']) && $params['status'] == 'published' ? 'selected' : '' }}>已发布</option>
                    <option value="draft" {{ isset($params['status']) && $params['status'] == 'draft' ? 'selected' : '' }}>草稿</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-control">
                    <option value="">全部分类</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" {{ isset($params['category']) && $params['category'] == $category ? 'selected' : '' }}>
                            {{ $category }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="/admin/simple_blog" class="btn btn-secondary">重置</a>
            </div>
        </div>
    </form>
</div>

{{-- 文章列表 --}}
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>分类</th>
                <th>标签</th>
                <th>状态</th>
                <th>浏览量</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse($posts as $post)
            <tr>
                <td>{{ $post->id }}</td>
                <td>
                    <strong>{{ $post->title }}</strong>
                    @if($post->is_featured)
                        <span class="badge bg-warning text-dark">精选</span>
                    @endif
                    @if($post->is_pinned)
                        <span class="badge bg-info">置顶</span>
                    @endif
                    @if($post->status == 'published')
                        <a href="/blog/post/{{ $post->id }}" target="_blank" class="text-muted small">
                            <i class="bi bi-box-arrow-up-right"></i> 查看
                        </a>
                    @endif
                </td>
                <td>{{ $post->category ?: '未分类' }}</td>
                <td>
                    @if(is_array($post->tags) && count($post->tags) > 0)
                        @foreach(array_slice($post->tags, 0, 3) as $tag)
                            <span class="badge bg-light text-dark">{{ $tag }}</span>
                        @endforeach
                        @if(count($post->tags) > 3)
                            <span class="badge bg-secondary">+{{ count($post->tags) - 3 }}</span>
                        @endif
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td>
                    @if($post->status == 'published')
                        <span class="badge bg-success">已发布</span>
                    @elseif($post->status == 'draft')
                        <span class="badge bg-warning text-dark">草稿</span>
                    @else
                        <span class="badge bg-secondary">{{ $post->status }}</span>
                    @endif
                </td>
                <td>{{ $post->view_count }}</td>
                <td>{{ $post->created_at }}</td>
                <td>
                    <a href="/admin/simple_blog/{{ $post->id }}/edit" class="btn btn-sm btn-info">
                        <i class="bi bi-pencil"></i> 编辑
                    </a>
                    <button class="btn btn-sm btn-danger" onclick="deletePost({{ $post->id }})">
                        <i class="bi bi-trash"></i> 删除
                    </button>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center">暂无文章</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- 分页 --}}
<div class="pagination-wrapper" style="text-align: center; margin-top: 20px;">
    {!! $posts->render() !!}
</div>
@endsection

@push('scripts')
<script>
function deletePost(id) {
    if (!confirm('确定要删除这篇文章吗？')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(`/admin/simple_blog/${id}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.code === 200) {
            alert('删除成功');
            location.reload();
        } else {
            alert(data.message || data.msg || '删除失败');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('删除失败');
    });
}
</script>
@endpush
