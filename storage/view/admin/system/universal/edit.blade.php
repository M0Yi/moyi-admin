@extends('admin.layouts.admin')

@section('title', '编辑' . ($config['title'] ?? '数据'))

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4" id="universal-edit-app">
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
    'formId' => 'editForm',
    'cancelUrl' => admin_route("u/{$model}"),
    'submitText' => '保存',
    'infoText' => '修改完成后点击保存按钮提交'
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
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js', 'version' => $universalFormJsVersion])
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



