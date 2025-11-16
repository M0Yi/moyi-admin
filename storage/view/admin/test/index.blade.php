@extends('admin.layouts.admin')

@section('title', '测试页')
<!-- 侧边栏 -->
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush
<!-- 顶栏 -->
@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h5 class="mb-0">后台测试页</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">此页面用于测试功能。</p>
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="{{ admin_route('dashboard') }}">返回仪表盘</a>
                        <a class="btn btn-outline-secondary" href="{{ admin_route('settings') }}">系统设置</a>
                    </div>
                    <hr/>
                    <p>示例内容：这里是一个简单的 Demo 单页，可根据需要替换。</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

