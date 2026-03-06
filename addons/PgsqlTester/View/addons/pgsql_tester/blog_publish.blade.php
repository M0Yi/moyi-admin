@extends('layouts.app')

@section('title', '发布博客文章')

@push('styles')
<style>
.blog-publish-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

textarea.form-control {
    min-height: 120px;
    resize: vertical;
}

.tags-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.tags-input:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.tags-hint {
    font-size: 12px;
    color: #666;
    margin-top: 3px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.3s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    margin-left: 10px;
}

.btn-secondary:hover {
    background-color: #545b62;
}

.form-actions {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid transparent;
    border-radius: 4px;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.alert-error {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.preview-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.preview-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #333;
}

.preview-content {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.preview-content h1,
.preview-content h2,
.preview-content h3,
.preview-content h4,
.preview-content h5,
.preview-content h6 {
    margin-top: 0;
    margin-bottom: 10px;
}

.preview-content p {
    margin-bottom: 10px;
    line-height: 1.6;
}
</style>
@endpush

@section('content')
<div class="blog-publish-container">
    <h1 style="text-align: center; margin-bottom: 30px; color: #333;">发布博客文章</h1>

    <!-- 错误消息提示 -->
    @if(isset($error))
    <div class="alert alert-error">
        {{ $error }}
    </div>
    @endif

    <!-- 消息提示 -->
    <div id="message" class="alert" style="display: none;"></div>

    <form id="blog-form" method="POST" action="/blog/publish">
        <!-- 文章标题 -->
        <div class="form-group">
            <label for="title">文章标题 *</label>
            <input type="text" class="form-control" id="title" name="title" required
                   placeholder="请输入文章标题" maxlength="200"
                   value="{{ $oldInput['title'] ?? '' }}">
        </div>

        <!-- 文章描述 -->
        <div class="form-group">
            <label for="description">文章描述 *</label>
            <textarea class="form-control" id="description" name="description" required
                      placeholder="请输入文章描述或摘要" maxlength="1000">{{ $oldInput['description'] ?? '' }}</textarea>
        </div>

        <!-- 文章分类 -->
        <div class="form-group">
            <label for="category">文章分类</label>
            <select class="form-control" id="category" name="category">
                <option value="">请选择分类</option>
                <option value="技术" {{ ($oldInput['category'] ?? '') === '技术' ? 'selected' : '' }}>技术</option>
                <option value="生活" {{ ($oldInput['category'] ?? '') === '生活' ? 'selected' : '' }}>生活</option>
                <option value="教程" {{ ($oldInput['category'] ?? '') === '教程' ? 'selected' : '' }}>教程</option>
                <option value="新闻" {{ ($oldInput['category'] ?? '') === '新闻' ? 'selected' : '' }}>新闻</option>
                <option value="其他" {{ ($oldInput['category'] ?? '') === '其他' ? 'selected' : '' }}>其他</option>
            </select>
        </div>

        <!-- 标签 -->
        <div class="form-group">
            <label for="tags">标签</label>
            <input type="text" class="tags-input" id="tags" name="tags"
                   placeholder="输入标签，用逗号分隔"
                   value="{{ $oldInput['tags'] ?? '' }}">
            <div class="tags-hint">例如：PHP, Web开发, 教程</div>
        </div>

        <!-- 发布选项 -->
        <div class="form-group">
            <label>
                <input type="checkbox" id="publish_now" name="publish_now"
                       {{ isset($oldInput['publish_now']) ? 'checked' : 'checked' }}>
                立即发布
            </label>
            <div style="font-size: 12px; color: #666; margin-top: 3px;">
                不勾选则保存为草稿
            </div>
        </div>

        <!-- 操作按钮 -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="submit-btn">
                <span id="submit-text">发布文章</span>
            </button>
            <button type="button" class="btn btn-secondary" onclick="previewArticle()">
                预览文章
            </button>
            <a href="/" class="btn btn-secondary">返回首页</a>
        </div>
    </form>

    <!-- 预览区域 -->
    <div class="preview-section" id="preview-section" style="display: none;">
        <h3 class="preview-title">文章预览</h3>
        <div class="preview-content">
            <h1 id="preview-title"></h1>
            <div id="preview-description"></div>
            <div style="margin-top: 15px;">
                <strong>分类：</strong><span id="preview-category"></span>
            </div>
            <div style="margin-top: 5px;">
                <strong>标签：</strong><span id="preview-tags"></span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('blog-form');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = document.getElementById('submit-text');
    const messageDiv = document.getElementById('message');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // 获取表单数据
        const formData = new FormData(form);
        const data = {
            title: formData.get('title').trim(),
            description: formData.get('description').trim(),
            category: formData.get('category'),
            tags: formData.get('tags').trim(),
            status: formData.get('publish_now') ? 'published' : 'draft'
        };

        // 基本验证
        if (!data.title) {
            showMessage('请输入文章标题', 'error');
            return;
        }

        if (!data.description) {
            showMessage('请输入文章描述', 'error');
            return;
        }

        // 处理标签
        if (data.tags) {
            data.tags = data.tags.split(',').map(tag => tag.trim()).filter(tag => tag);
        } else {
            data.tags = [];
        }

        // 显示加载状态
        submitBtn.disabled = true;
        submitText.textContent = '发布中...';

        try {
            const response = await fetch('/api/pgsql_tester/blog/create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.code === 200) {
                showMessage('文章发布成功！', 'success');
                form.reset();
                document.getElementById('preview-section').style.display = 'none';
            } else {
                showMessage(result.msg || result.message || '发布失败', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('网络错误，请稍后重试', 'error');
        } finally {
            submitBtn.disabled = false;
            submitText.textContent = '发布文章';
        }
    });

    // 发布选项变化时更新按钮文本
    document.getElementById('publish_now').addEventListener('change', function() {
        submitText.textContent = this.checked ? '发布文章' : '保存草稿';
    });
});

function showMessage(message, type) {
    const messageDiv = document.getElementById('message');
    messageDiv.textContent = message;
    messageDiv.className = `alert alert-${type}`;
    messageDiv.style.display = 'block';

    // 3秒后自动隐藏
    setTimeout(() => {
        messageDiv.style.display = 'none';
    }, 3000);

    // 滚动到消息位置
    messageDiv.scrollIntoView({ behavior: 'smooth' });
}

function previewArticle() {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    const category = document.getElementById('category').value;
    const tags = document.getElementById('tags').value.trim();

    if (!title && !description) {
        showMessage('请先输入标题和描述再预览', 'error');
        return;
    }

    // 更新预览内容
    document.getElementById('preview-title').textContent = title || '无标题';
    document.getElementById('preview-description').innerHTML = description ? description.replace(/\n/g, '<br>') : '无描述';
    document.getElementById('preview-category').textContent = category || '未分类';
    document.getElementById('preview-tags').textContent = tags || '无标签';

    // 显示预览区域
    document.getElementById('preview-section').style.display = 'block';

    // 滚动到预览区域
    document.getElementById('preview-section').scrollIntoView({ behavior: 'smooth' });
}
</script>
@endpush
