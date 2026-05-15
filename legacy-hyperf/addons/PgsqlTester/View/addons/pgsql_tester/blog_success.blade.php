@extends('layouts.app')

@section('title', '发布成功')

@section('content')
<div class="blog-success-container" style="max-width: 600px; margin: 0 auto; padding: 40px 20px; text-align: center;">
    <div class="success-icon" style="font-size: 64px; color: #28a745; margin-bottom: 20px;">
        ✓
    </div>

    <h1 style="color: #28a745; margin-bottom: 20px;">发布成功！</h1>

    <div class="success-message" style="background-color: #d4edda; color: #155724; padding: 20px; border-radius: 8px; border: 1px solid #c3e6cb; margin-bottom: 30px;">
        <h4>{{ $message ?? '博客文章已成功发布！' }}</h4>
    </div>

    @if(isset($blog))
    <div class="blog-info" style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 30px;">
        <h5>文章信息：</h5>
        <p><strong>标题：</strong> {{ $blog->title }}</p>
        <p><strong>状态：</strong>
            @if($blog->status === 'published')
                <span style="color: #28a745;">已发布</span>
            @else
                <span style="color: #ffc107;">草稿</span>
            @endif
        </p>
        <p><strong>发布时间：</strong> {{ $blog->created_at->format('Y-m-d H:i:s') }}</p>
    </div>
    @endif

    <div class="actions">
        <a href="/blog/publish" class="btn" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;">
            继续发布
        </a>
        <a href="/" class="btn" style="background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">
            返回首页
        </a>
    </div>
</div>
@endsection