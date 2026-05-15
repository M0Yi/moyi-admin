@extends('layouts.app')

@section('title', '我的博客 - 分享技术与生活的精彩瞬间')

@push('styles')
<style>
    :root {
        --primary-color: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary-color: #f97316;
        --accent-color: #06b6d4;
        --text-dark: #1f2937;
        --text-gray: #6b7280;
        --text-light: #9ca3af;
        --bg-light: #f9fafb;
        --bg-white: #ffffff;
        --border-color: #e5e7eb;
        --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        --transition: all 0.3s ease;
    }
    .site-header { background: rgba(255,255,255,0.95); box-shadow: 0 1px 3px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; border-bottom: 3px solid var(--secondary-color); }
    .header-inner { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; height: 64px; }
    .logo-area { display: flex; align-items: center; gap: 12px; }
    .logo-image { height: 42px; width: auto; object-fit: contain; }
    .main-nav { display: flex; align-items: center; gap: 8px; }
    .nav-item { display: flex; align-items: center; gap: 8px; padding: 10px 20px; color: var(--text-dark); font-size: 15px; font-weight: 500; text-decoration: none; position: relative; transition: var(--transition); }
    .nav-item:hover, .nav-item.active { color: var(--secondary-color); }
    .nav-icon { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
    .nav-icon svg { width: 20px; height: 20px; }
    .search-area { display: flex; align-items: center; gap: 0; }
    .search-input { width: 220px; height: 36px; padding: 0 16px; border: 1px solid var(--border-color); border-radius: 18px 0 0 18px; font-size: 14px; background: #fff; outline: none; transition: var(--transition); }
    .search-input:focus { border-color: var(--secondary-color); }
    .search-btn { width: 40px; height: 36px; background: var(--secondary-color); border: 1px solid var(--secondary-color); border-radius: 0 18px 18px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); }
    .search-btn:hover { background: #ea580c; }
    .search-btn svg { width: 18px; height: 18px; color: white; }
    .hero-section { background: linear-gradient(135deg, var(--primary-color) 0%, #7c3aed 50%, var(--accent-color) 100%); color: white; padding: 5rem 1.5rem; text-align: center; position: relative; overflow: hidden; }
    .hero-section::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); opacity: 0.5; }
    .hero-content { position: relative; max-width: 800px; margin: 0 auto; }
    .hero-badge { display: inline-block; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 0.5rem 1.25rem; border-radius: 50px; font-size: 0.875rem; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.2); }
    .hero-title { font-size: 3rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.2; }
    .hero-subtitle { font-size: 1.25rem; opacity: 0.9; margin-bottom: 2rem; }
    .hero-stats { display: flex; justify-content: center; gap: 3rem; margin-top: 3rem; }
    .stat-item { text-align: center; }
    .stat-number { font-size: 2.5rem; font-weight: 700; }
    .stat-label { font-size: 0.875rem; opacity: 0.8; }
    .featured-section { padding: 4rem 1.5rem; max-width: 1280px; margin: 0 auto; }
    .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
    .section-title { font-size: 1.75rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
    .section-title-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--secondary-color), #fbbf24); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: white; font-size: 1rem; }
    .featured-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .featured-card { background: var(--bg-white); border-radius: var(--radius-xl); overflow: hidden; box-shadow: var(--shadow-md); transition: var(--transition); }
    .featured-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-xl); }
    .featured-card-main { grid-column: span 2; grid-row: span 2; }
    .featured-image { height: 180px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); position: relative; overflow: hidden; }
    .featured-badge { position: absolute; top: 1rem; left: 1rem; background: var(--secondary-color); color: white; padding: 0.375rem 0.75rem; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .featured-content { padding: 1.5rem; }
    .featured-category { color: var(--primary-color); font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; }
    .featured-title { font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.75rem; line-height: 1.4; }
    .featured-card-main .featured-title { font-size: 1.75rem; }
    .featured-excerpt { color: var(--text-gray); font-size: 0.9rem; line-height: 1.7; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .featured-meta { display: flex; align-items: center; gap: 1rem; font-size: 0.875rem; color: var(--text-light); }
    .author-avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 600; }
    .main-content { max-width: 1280px; margin: 0 auto; padding: 2rem 1.5rem; display: grid; grid-template-columns: 1fr 340px; gap: 2rem; }
    .latest-section { background: var(--bg-white); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-md); }
    .article-list { display: flex; flex-direction: column; gap: 1.5rem; }
    .article-item { display: flex; gap: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); transition: var(--transition); }
    .article-item:last-child { border-bottom: none; padding-bottom: 0; }
    .article-item:hover { transform: translateX(8px); }
    .article-thumbnail { width: 200px; height: 140px; border-radius: var(--radius-lg); overflow: hidden; flex-shrink: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .article-info { flex: 1; display: flex; flex-direction: column; }
    .article-category { display: inline-block; color: var(--primary-color); font-size: 0.8rem; font-weight: 600; margin-bottom: 0.5rem; }
    .article-title { font-size: 1.125rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.75rem; line-height: 1.4; transition: var(--transition); }
    .article-item:hover .article-title { color: var(--primary-color); }
    .article-excerpt { color: var(--text-gray); font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .article-meta { display: flex; align-items: center; gap: 1rem; font-size: 0.8rem; color: var(--text-light); }
    .article-meta span { display: flex; align-items: center; gap: 0.375rem; }
    .sidebar { display: flex; flex-direction: column; gap: 1.5rem; }
    .sidebar-widget { background: var(--bg-white); border-radius: var(--radius-xl); padding: 1.5rem; box-shadow: var(--shadow-md); }
    .widget-title { font-size: 1.125rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }
    .widget-title::before { content: ''; width: 4px; height: 20px; background: var(--primary-color); border-radius: 2px; }
    .category-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .category-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: var(--bg-light); border-radius: var(--radius-md); transition: var(--transition); }
    .category-item:hover { background: var(--primary-color); color: white; }
    .category-link { display: flex; align-items: center; gap: 0.75rem; font-weight: 500; }
    .category-icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary-light), var(--accent-color)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.875rem; }
    .category-count { background: rgba(0,0,0,0.1); padding: 0.25rem 0.625rem; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
    .tag-cloud { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .tag-item { padding: 0.5rem 1rem; background: var(--bg-light); border-radius: 50px; font-size: 0.875rem; color: var(--text-gray); transition: var(--transition); }
    .tag-item:hover { background: var(--primary-color); color: white; }
    .recent-list { display: flex; flex-direction: column; gap: 1rem; }
    .recent-item { display: flex; gap: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
    .recent-item:last-child { border-bottom: none; padding-bottom: 0; }
    .recent-thumb { width: 80px; height: 60px; border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .recent-title { font-size: 0.9rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.375rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .recent-date { font-size: 0.75rem; color: var(--text-light); }
    .hot-list { display: flex; flex-direction: column; gap: 1rem; }
    .hot-item { display: flex; align-items: center; gap: 1rem; }
    .hot-rank { width: 28px; height: 28px; background: linear-gradient(135deg, var(--secondary-color), #fbbf24); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
    .hot-item:nth-child(1) .hot-rank { background: linear-gradient(135deg, #f97316, #fbbf24); }
    .hot-item:nth-child(2) .hot-rank { background: linear-gradient(135deg, #6b7280, #9ca3af); }
    .hot-item:nth-child(3) .hot-rank { background: linear-gradient(135deg, #b45309, #d97706); }
    .hot-title { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); line-height: 1.4; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pagination-wrap { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: center; }
    .pagination { display: flex; align-items: center; gap: 0.5rem; }
    .page-item { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); font-weight: 500; color: var(--text-gray); transition: var(--transition); }
    .page-item:hover, .page-item.active { background: var(--primary-color); color: white; }
    .site-footer { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 4rem 1.5rem 2rem; margin-top: 4rem; }
    .footer-inner { max-width: 1200px; margin: 0 auto; }
    .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; margin-bottom: 3rem; }
    .footer-brand { max-width: 300px; }
    .footer-logo { display: flex; align-items: center; gap: 0.75rem; font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 1rem; }
    .footer-logo-image { height: 40px; width: auto; object-fit: contain; }
    .footer-desc { color: #94a3b8; font-size: 0.9rem; line-height: 1.7; }
    .footer-title { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; color: white; }
    .footer-links { display: flex; flex-direction: column; gap: 0.75rem; }
    .footer-link { color: #94a3b8; font-size: 0.9rem; transition: var(--transition); }
    .footer-link:hover { color: white; }
    .footer-bottom { padding-top: 2rem; border-top: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
    .copyright { color: #64748b; font-size: 0.875rem; }
    .social-links { display: flex; gap: 1rem; }
    .social-link { width: 40px; height: 40px; background: #334155; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: #94a3b8; transition: var(--transition); }
    .social-link:hover { background: var(--primary-color); color: white; }
    @media (max-width: 1024px) { .main-content { grid-template-columns: 1fr; } .sidebar { display: grid; grid-template-columns: repeat(2, 1fr); } .footer-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .header-inner { flex-wrap: wrap; height: auto; padding: 1rem; gap: 1rem; } .main-nav { order: 3; width: 100%; justify-content: center; gap: 0.5rem; flex-wrap: wrap; } .nav-item { padding: 8px 12px; font-size: 14px; } .search-area { order: 2; } .search-input { width: 160px; } .hero-title { font-size: 2rem; } .hero-stats { gap: 1.5rem; } .stat-number { font-size: 1.75rem; } .featured-grid { grid-template-columns: 1fr; } .featured-card-main { grid-column: span 1; grid-row: span 1; } .featured-card-main .featured-image { height: 200px; } .article-item { flex-direction: column; } .article-thumbnail { width: 100%; height: 200px; } .sidebar { grid-template-columns: 1fr; } .footer-grid { grid-template-columns: 1fr; gap: 2rem; } .footer-bottom { flex-direction: column; gap: 1rem; text-align: center; } }
    @media (max-width: 480px) { .hero-section { padding: 3rem 1rem; } .hero-title { font-size: 1.75rem; } .section-title { font-size: 1.25rem; } }
    .empty-state { text-align: center; padding: 4rem 2rem; }
    .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
    .empty-title { font-size: 1.25rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.5rem; }
    .empty-desc { color: var(--text-gray); }
</style>
@endpush

@section('content')
<header class="site-header">
    <div class="header-inner">
        <a href="/blog" class="logo-area">
            <img src="http://www.jianhuicishan.org/_nuxt/img/logo-new.115df95.png" alt="Logo" class="logo-image">
        </a>
        <nav class="main-nav">
            <a href="/blog" class="nav-item {{ $isHome ? 'active' : '' }}">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
                首页
            </a>
            <a href="/blog?featured=1" class="nav-item {{ $isFeatured ? 'active' : '' }}">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></span>
                精选
            </a>
            <a href="/blog?sort_by=view_count" class="nav-item {{ $isHot ? 'active' : '' }}">
                <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 6l-9.5 9.5-5-5L1 18"></path><path d="M17 6h6v6"></path></svg></span>
                热门
            </a>
        </nav>
        <form action="/blog" method="GET" class="search-area">
            <input type="text" name="keyword" class="search-input" placeholder="搜索文章..." value="{{ $params['keyword'] ?? '' }}">
            <button type="submit" class="search-btn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
        </form>
    </div>
</header>

<section class="hero-section">
    <div class="hero-content">
        <span class="hero-badge">欢迎来到我的博客</span>
        <h1 class="hero-title">分享技术与生活的精彩瞬间</h1>
        <p class="hero-subtitle">记录成长，分享智慧，让思想在这里碰撞出火花</p>
        <div class="hero-stats">
            <div class="stat-item"><div class="stat-number">{{ count($posts) > 0 ? $posts->total() : '0' }}</div><div class="stat-label">篇文章</div></div>
            <div class="stat-item"><div class="stat-number">{{ count($categories) }}</div><div class="stat-label">个分类</div></div>
            <div class="stat-item"><div class="stat-number">{{ count($tags) }}</div><div class="stat-label">个标签</div></div>
        </div>
    </div>
</section>

@if(!empty($featuredPosts) && count($featuredPosts) > 0)
<section class="featured-section">
    <div class="section-header">
        <h2 class="section-title"><span class="section-title-icon">★</span>精选文章</h2>
        <a href="/blog?featured=1" style="color: var(--primary-color); font-weight: 500;">查看更多 →</a>
    </div>
    <div class="featured-grid">
        @foreach($featuredPosts as $index => $post)
        <a href="/blog/post/{{ $post->id }}" class="featured-card {{ $index === 0 ? 'featured-card-main' : '' }}">
            <div class="featured-image" style="background: linear-gradient(135deg, hsl({{ rand(200, 280) }}, 70%, 60%) 0%, hsl({{ rand(280, 340) }}, 70%, 60%) 100%);">
                @if($post->is_featured)<span class="featured-badge">精选</span>@elseif($post->is_pinned)<span class="featured-badge">置顶</span>@endif
            </div>
            <div class="featured-content">
                <div class="featured-category">{{ $post->category ?: '未分类' }}</div>
                <h3 class="featured-title">{{ $post->title }}</h3>
                <p class="featured-excerpt">{{ $post->summary ?: strip_tags(substr($post->content ?? '', 0, 200)) }}...</p>
                <div class="featured-meta">
                    <div class="author-avatar">作</div>
                    <span>{{ $post->formatted_created_at ?? date('Y-m-d', strtotime($post->created_at)) }}</span>
                    <span>阅读 {{ $post->view_count ?? 0 }}</span>
                </div>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif

<div class="main-content">
    <div class="latest-section">
        <div class="section-header"><h2 class="section-title"><span class="section-title-icon">📝</span>最新发布</h2></div>
        @forelse($posts as $post)
        <article class="article-item">
            <a href="/blog/post/{{ $post->id }}" class="article-thumbnail" style="background: linear-gradient(135deg, hsl({{ rand(200, 280) }}, 70%, 60%) 0%, hsl({{ rand(280, 340) }}, 70%, 60%) 100%);"></a>
            <div class="article-info">
                <a href="/blog/category/{{ urlencode($post->category) }}" class="article-category">{{ $post->category ?: '未分类' }}</a>
                <a href="/blog/post/{{ $post->id }}"><h3 class="article-title">{{ $post->title }}</h3></a>
                <p class="article-excerpt">{{ $post->summary ?: strip_tags(substr($post->content ?? '', 0, 150)) }}...</p>
                <div class="article-meta">
                    <span>📅 {{ $post->formatted_created_at }}</span>
                    <span>👁️ {{ $post->view_count ?? 0 }}</span>
                    <span>❤️ {{ $post->like_count ?? 0 }}</span>
                </div>
            </div>
        </article>
        @empty
        <div class="empty-state"><div class="empty-icon">📭</div><h3 class="empty-title">暂无文章</h3><p class="empty-desc">还没有发布任何文章内容，敬请期待！</p></div>
        @endforelse
        @if($posts->hasPages())
        <div class="pagination-wrap">
            <nav class="pagination">
                @if($posts->currentPage() > 1)<a href="{{ $posts->url(1) }}" class="page-item">«</a><a href="{{ $posts->previousPageUrl() }}" class="page-item">‹</a>@endif
                @for($i = max(1, $posts->currentPage() - 2); $i <= min($posts->lastPage(), $posts->currentPage() + 2); $i++)
                    @if($i == $posts->currentPage())<span class="page-item active">{{ $i }}</span>@else<a href="{{ $posts->url($i) }}" class="page-item">{{ $i }}</a>@endif
                @endfor
                @if($posts->currentPage() < $posts->lastPage())<a href="{{ $posts->nextPageUrl() }}" class="page-item">›</a><a href="{{ $posts->url($posts->lastPage()) }}" class="page-item">»</a>@endif
            </nav>
        </div>
        @endif
    </div>

    <aside class="sidebar">
        <div class="sidebar-widget">
            <h3 class="widget-title">文章分类</h3>
            <div class="category-list">
                <a href="/blog" class="category-item"><span class="category-link"><span class="category-icon">📁</span>全部文章</span><span class="category-count">{{ $posts->total() }}</span></a>
                @foreach($categories as $category)
                <a href="/blog/category/{{ urlencode($category) }}" class="category-item"><span class="category-link"><span class="category-icon">📄</span>{{ $category }}</span></a>
                @endforeach
            </div>
        </div>
        @if(!empty($tags))
        <div class="sidebar-widget">
            <h3 class="widget-title">标签云</h3>
            <div class="tag-cloud">
                @foreach($tags as $tag)<a href="/blog?tag={{ urlencode($tag) }}" class="tag-item">{{ $tag }}</a>@endforeach
            </div>
        </div>
        @endif
        <div class="sidebar-widget">
            <h3 class="widget-title">最新文章</h3>
            <div class="recent-list">
                @foreach($latestPosts->take(4) as $recent)
                <a href="/blog/post/{{ $recent->id }}" class="recent-item">
                    <div class="recent-thumb" style="background: linear-gradient(135deg, hsl({{ rand(200, 280) }}, 70%, 60%) 0%, hsl({{ rand(280, 340) }}, 70%, 60%) 100%);"></div>
                    <div class="recent-info"><h4 class="recent-title">{{ $recent->title }}</h4><span class="recent-date">{{ $recent->formatted_created_at }}</span></div>
                </a>
                @endforeach
            </div>
        </div>
        <div class="sidebar-widget">
            <h3 class="widget-title">热门文章</h3>
            <div class="hot-list">
                @foreach($posts->sortByDesc('view_count')->take(5) as $index => $hot)
                <a href="/blog/post/{{ $hot->id }}" class="hot-item"><span class="hot-rank">{{ $index + 1 }}</span><span class="hot-title">{{ $hot->title }}</span></a>
                @endforeach
            </div>
        </div>
    </aside>
</div>

<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-brand">
                <div class="footer-logo"><img src="http://www.jianhuicishan.org/_nuxt/img/logo-new.115df95.png" alt="Logo" class="footer-logo-image"></div>
                <p class="footer-desc">这是一个专注于技术和生活的个人博客，分享有价值的文章和想法。希望我的文字能给你带来一些启发和帮助。</p>
            </div>
            <div><h4 class="footer-title">快速导航</h4><div class="footer-links"><a href="/blog" class="footer-link">首页</a><a href="/blog?featured=1" class="footer-link">精选文章</a><a href="/blog?sort_by=view_count" class="footer-link">热门文章</a></div></div>
            <div><h4 class="footer-title">文章分类</h4><div class="footer-links">@foreach(array_slice($categories, 0, 5) as $category)<a href="/blog/category/{{ urlencode($category) }}" class="footer-link">{{ $category }}</a>@endforeach</div></div>
            <div><h4 class="footer-title">联系方式</h4><div class="footer-links"><span class="footer-link">📧 博主邮箱@example.com</span><span class="footer-link">📍 所在城市</span></div></div>
        </div>
        <div class="footer-bottom">
            <p class="copyright">© {{ date('Y') }} 我的博客 · 用心写作 · 用爱生活</p>
            <div class="social-links">
                <a href="#" class="social-link" title="GitHub"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg></a>
                <a href="#" class="social-link" title="微博"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M10.098 20c-4.27 0-8.598-2.115-8.598-6.195 0-2.737 1.405-5.037 3.734-6.437-1.066 2.336-1.47 4.654-1.47 5.98 0 4.039 3.322 6.65 7.215 6.65 1.832 0 3.457-.49 4.414-1.14-.01-.16-.016-.33-.016-.5 0-3.37-4.6-5.53-4.6-8.01 0-1.76 1.25-3.24 2.94-3.24 1.69 0 2.65 1.48 2.65 3.24 0 2.48-4.6 4.64-4.6 8.01 0 .64.18 1.24.5 1.77.9-.47 2.04-.74 3.23-.74 4.89 0 7.21 2.61 7.21 6.65 0 4.04-4.33 6.195-8.598 6.195-.43 0-.86-.03-1.28-.08 2.43-1.69 3.92-3.91 3.92-6.42 0-3.17-2.63-5.04-5.34-5.04-2.17 0-3.99 1.06-5.18 2.67.62-.07 1.25-.1 1.91-.1z"/></svg></a>
                <a href="#" class="social-link" title="微信"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 01.213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 00.167-.054l1.903-1.114a.864.864 0 01.717-.098 10.16 10.16 0 002.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178A1.17 1.17 0 014.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178 1.17 1.17 0 01-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 01.598.082l1.584.926a.272.272 0 00.14.047c.134 0 .24-.111.24-.247 0-.06-.024-.12-.04-.178l-.327-1.233a.582.582 0 01-.023-.156.49.49 0 01.201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89l-.006-.033zm-2.634 2.588c.535 0 .969.44.969.982a.976.976 0 01-.969.983.976.976 0 01-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 01-.969.983.976.976 0 01-.969-.983c0-.542.434-.982.969-.982z"/></svg></a>
            </div>
        </div>
    </div>
</footer>
@endsection
