@extends('admin.layouts.admin')

@section('title', '添加' . ($config['title'] ?? '数据'))

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
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route("universal/{$model}"),
    'submitText' => '保存',
    'formId' => 'createForm',
    'submitBtnId' => 'submitBtn'
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
@php
    $universalFormJsVersion = file_exists(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        ? filemtime(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        : time();
@endphp
<script src="/js/components/universal-form-renderer.js?v={{ $universalFormJsVersion }}"></script>
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