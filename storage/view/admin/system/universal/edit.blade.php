@extends('admin.layouts.admin')

@section('title', '编辑' . ($config['title'] ?? '数据'))

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4" id="universal-edit-app">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">编辑{{ $config['title'] ?? '数据' }}</h6>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ admin_route('dashboard') }}">首页</a></li>
                <li class="breadcrumb-item"><a href="#">系统管理</a></li>
                <li class="breadcrumb-item"><a href="{{ admin_route("u/{$model}") }}">{{ $config['title'] ?? '数据' }}列表</a></li>
                <li class="breadcrumb-item active">编辑</li>
            </ol>
        </nav>
    </div>

    <!-- 表单卡片 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-muted mb-3" id="universalFormLoading">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span>表单配置加载中，请稍候...</span>
                    </div>
                    <form id="editForm" class="d-none">
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="id" value="{{ $recordId }}">
                        <div class="row" id="universalFormFields"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '修改完成后点击保存按钮提交',
    'cancelUrl' => admin_route("u/{$model}"),
    'submitText' => '保存',
    'formId' => 'editForm',
    'submitBtnId' => 'submitBtn'
])
@push('admin-styles')
<style>
#universalFormFields .form-label {
    font-weight: 600;
}

.universal-form-field + .form-text {
    margin-top: 0.35rem;
}
</style>
@endpush

@push('admin_scripts')
@php
    $universalFormJsVersion = file_exists(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        ? filemtime(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        : time();
@endphp
<script src="/js/components/universal-form-renderer.js?v={{ $universalFormJsVersion }}"></script>
<script>
window.UniversalEditPage = {
    model: '{{ $model }}',
    config: {!! $configJson ?? '{}' !!},
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[UniversalEdit] UniversalFormRenderer 未正确加载');
            return;
        }
        
    new UniversalFormRenderer({
        schema: window.UniversalEditPage.formSchema,
        config: window.UniversalEditPage.config,
        formId: 'editForm',
        fieldsWrapperSelector: '#universalFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'universalFormLoading'
    });
});
</script>
@endpush
@endsection



