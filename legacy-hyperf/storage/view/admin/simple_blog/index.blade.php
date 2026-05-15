@extends('admin.layouts.admin')

@section('title', '博客文章管理')

@php
    $blogSearchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($blogSearchConfig['search_fields'] ?? []);
@endphp

@if (! ($isEmbedded ?? false))
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
<div class="container-fluid py-4">
    {{-- 统计信息 --}}
    <div class="row mb-4">
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

    <div class="mb-3">
        <h6 class="mb-1 fw-bold">博客文章管理</h6>
        <small class="text-muted">管理博客文章列表</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'simpleBlogTable',
                'storageKey' => 'simpleBlogTableColumns',
                'ajaxUrl' => '/api/simple_blog/posts',
                'searchFormId' => 'searchForm_simpleBlogTable',
                'searchPanelId' => 'searchPanel_simpleBlogTable',
                'searchConfig' => $blogSearchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    ['index'=>0,'label'=>'ID','field'=>'id','type'=>'number','visible'=>true,'width'=>'60'],
                    ['index'=>1,'label'=>'标题','field'=>'title','type'=>'text','visible'=>true,'width'=>'200'],
                    ['index'=>2,'label'=>'分类','field'=>'category','type'=>'badge','visible'=>true,'options'=>[],'width'=>'100'],
                    ['index'=>3,'label'=>'标签','field'=>'tags','type'=>'custom','renderFunction'=>'renderTags','visible'=>true,'width'=>'150'],
                    ['index'=>4,'label'=>'状态','field'=>'status','type'=>'custom','renderFunction'=>'renderStatus','visible'=>true,'width'=>'100'],
                    ['index'=>5,'label'=>'浏览量','field'=>'view_count','type'=>'number','visible'=>true,'width'=>'80'],
                    ['index'=>6,'label'=>'发布时间','field'=>'published_at','type'=>'datetime','format'=>'Y-m-d H:i','visible'=>true,'width'=>'150'],
                    ['index'=>7,'label'=>'创建时间','field'=>'created_at','type'=>'date','format'=>'Y-m-d H:i:s','visible'=>true,'width'=>'150'],
                    ['index'=>8,'label'=>'操作','type'=>'actions','actions'=>[
                        [
                            'type' => 'link',
                            'href' => admin_route('simple_blog') . '/{id}/edit',
                            'icon' => 'bi-pencil',
                            'variant' => 'warning',
                            'title' => '编辑',
                            'attributes' => [
                                'data-iframe-shell-trigger' => 'simple_blog-edit-{id}',
                                'data-iframe-shell-src' => admin_route('simple_blog') . '/{id}/edit',
                                'data-iframe-shell-title' => '编辑文章',
                                'data-iframe-shell-channel' => 'simple_blog',
                                'data-iframe-shell-hide-actions' => 'true'
                            ]
                        ],
                        ['type'=>'button','onclick'=>'deleteRow_simpleBlogTable({id})','icon'=>'bi-trash','variant'=>'danger','title'=>'删除'],
                    ],'visible'=>true,'width'=>'120','class'=>'sticky-column','toggleable'=>false],
                ],
                'data'=>[],
                'emptyMessage'=>'暂无文章',
                'leftButtons' => [
                    [
                        'type' => 'button',
                        'text' => '新建文章',
                        'icon' => 'bi-plus-lg',
                        'variant' => 'primary',
                        'attributes' => [
                            'data-iframe-shell-trigger' => 'simple_blog-create',
                            'data-iframe-shell-src' => admin_route('simple_blog') . '/create',
                            'data-iframe-shell-title' => '新建文章',
                            'data-iframe-shell-channel' => 'simple_blog',
                            'data-iframe-shell-hide-actions' => 'true'
                        ]
                    ],
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin-styles')
<style>
.stats-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stats-number {
    font-size: 24px;
    font-weight: bold;
    color: var(--primary-color);
}
</style>
@endpush

@push('admin_scripts')
@if ($hasSearchConfig)
    @include('components.admin-script', ['path' => '/js/components/search-form-renderer.js'])
@endif
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
function renderStatus(value, row) {
    if (value === 'published' || value === 1 || value === '1') {
        return '<span class="badge bg-success">已发布</span>';
    }
    if (value === 'draft' || value === 0 || value === '0') {
        return '<span class="badge bg-warning text-dark">草稿</span>';
    }
    return '<span class="badge bg-secondary">' + value + '</span>';
}

function renderTags(value, row) {
    if (!value || !Array.isArray(value) || value.length === 0) {
        return '<span class="text-muted">-</span>';
    }
    var tags = value.slice(0, 3).map(function(tag) {
        return '<span class="badge bg-light text-dark">' + tag + '</span>';
    });
    if (value.length > 3) {
        tags.push('<span class="badge bg-secondary">+' + (value.length - 3) + '</span>');
    }
    return tags.join(' ');
}

document.addEventListener('DOMContentLoaded', function () {
    window.deleteRow_simpleBlogTable = function(id) {
        if (!confirm('确定要删除这篇文章吗？')) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(`/admin/simple_blog/${id}`, {
            method: 'DELETE',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}
        }).then(r=>r.json()).then(data=>{
            if (data.code===200) { alert('删除成功'); location.reload(); } else { alert(data.msg||'删除失败'); }
        }).catch(()=>alert('删除失败'));
    };

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($blogSearchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_simpleBlogTable',
                panelId: 'searchPanel_simpleBlogTable',
                tableId: 'simpleBlogTable'
            });

            window['_searchFormRenderer_simpleBlogTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_simpleBlogTable = window.createSearchFormResetFunction('simpleBlogTable');
            } else {
                window.resetSearchForm_simpleBlogTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[SimpleBlogPage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
