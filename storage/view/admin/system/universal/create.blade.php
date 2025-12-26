@extends('admin.layouts.admin')

@section('title', '添加' . ($config['title'] ?? '数据'))

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4" id="universal-create-app">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-muted mb-3" id="universalFormLoading">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span>表单配置加载中，请稍候...</span>
                            </div>
                    <form id="createForm" class="d-none">
                        <div class="row" id="universalFormFields"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'formId' => 'createForm',
    'cancelUrl' => admin_route("universal/{$model}"),
    'submitText' => '保存',
    'infoText' => '填写完成后点击保存按钮提交'
])
@endsection

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
<!-- 引入图标选择器组件 -->
@include('components.icon-picker')

@php
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js'])
<script>
window.UniversalCreatePage = {
    model: '{{ $model }}',
    config: {!! $configJson ?? '{}' !!},
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[UniversalCreate] UniversalFormRenderer 未正确加载');
            return;
        }
        
    new UniversalFormRenderer({
        schema: window.UniversalCreatePage.formSchema,
        config: window.UniversalCreatePage.config,
        formId: 'createForm',
        fieldsWrapperSelector: '#universalFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'universalFormLoading'
    });
    });
</script>
@endpush