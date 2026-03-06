@extends('admin.layouts.admin')

@section('title', '创建文章')

@push('styles')
<style>
    .form-container {
        background: #fff;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
    }
    .form-label.required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
    }
    .help-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 4px;
    }
    .tag-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }
    .tag-item {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        background: #e5e7eb;
        border-radius: 16px;
        font-size: 14px;
    }
    .tag-item .remove-tag {
        cursor: pointer;
        color: #6b7280;
    }
    .tag-item .remove-tag:hover {
        color: #ef4444;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1>创建文章</h1>
    <div class="actions">
        <a href="/admin/simple_blog" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> 返回列表
        </a>
    </div>
</div>

<div class="form-container">
    <form id="postForm" action="/admin/simple_blog" method="POST">
        <input type="hidden" name="_token" value="{{ $csrfToken ?? csrf_token() }}">
        <input type="hidden" name="_method" value="POST">

        <div class="row">
            {{-- 左侧：主要内容 --}}
            <div class="col-md-8">
                <div class="mb-3">
                    <label class="form-label required">文章标题</label>
                    <input type="text" name="title" class="form-control" placeholder="请输入文章标题" required>
                </div>

                <div class="mb-3">
                    <label class="form-label required">文章内容</label>
                    <textarea name="content" id="contentEditor" class="form-control" rows="15" placeholder="请输入文章内容" required></textarea>
                </div>
            </div>

            {{-- 右侧：设置 --}}
            <div class="col-md-4">
                <div class="mb-3">
                    <label class="form-label">分类</label>
                    <select name="category" class="form-select">
                        <option value="">未分类</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">标签</label>
                    <div class="tag-input-wrapper">
                        <input type="text" id="tagInput" class="form-control" placeholder="输入标签后按回车">
                        <input type="hidden" name="tags" id="tagsInput" value="[]">
                    </div>
                    <div class="tag-container" id="tagContainer"></div>
                    <p class="help-text">输入标签后按回车添加，最多5个标签</p>
                </div>

                <div class="mb-3">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="draft">草稿</option>
                        <option value="published">立即发布</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <input type="checkbox" name="is_featured" value="1"> 精选文章
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        <input type="checkbox" name="is_pinned" value="1"> 置顶文章
                    </label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg"></i> 发布文章
                    </button>
                    <a href="/admin/simple_blog" class="btn btn-secondary">取消</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 标签管理
    const tagInput = document.getElementById('tagInput');
    const tagContainer = document.getElementById('tagContainer');
    const tagsInput = document.getElementById('tagsInput');
    let tags = [];

    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const tag = this.value.trim();
            if (tag && tags.length < 5 && !tags.includes(tag)) {
                tags.push(tag);
                renderTags();
                this.value = '';
            }
        }
    });

    function renderTags() {
        tagContainer.innerHTML = tags.map(tag => `
            <span class="tag-item">
                ${tag}
                <span class="remove-tag" onclick="removeTag('${tag}')">&times;</span>
            </span>
        `).join('');
        tagsInput.value = JSON.stringify(tags);
    }

    window.removeTag = function(tag) {
        tags = tags.filter(t => t !== tag);
        renderTags();
    };

    // 表单提交
    document.getElementById('postForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = {
            title: formData.get('title'),
            content: formData.get('content'),
            category: formData.get('category') || '',
            tags: JSON.parse(formData.get('tags') || '[]'),
            status: formData.get('status') || 'draft',
            is_featured: formData.get('is_featured') === 'on' ? 1 : 0,
            is_pinned: formData.get('is_pinned') === 'on' ? 1 : 0,
        };

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('/admin/simple_blog', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert('文章创建成功');
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            } else {
                alert(data.message || data.msg || '创建失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('创建失败');
        });
    });
});
</script>
@endpush
