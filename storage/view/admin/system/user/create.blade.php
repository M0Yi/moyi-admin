@extends('admin.layouts.admin')

@section('title', '新增用户')

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
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">新增用户</h6>
        <small class="text-muted">创建新的系统用户</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="userFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
            </div>
            <form id="userForm" class="d-none">
                <div class="row" id="userFormFields"></div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/users'),
    'submitText' => '保存',
    'formId' => 'userForm',
    'submitBtnId' => 'submitBtn'
])
@endsection

@push('admin_scripts')
@php
    $universalFormJsVersion = file_exists(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        ? filemtime(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        : time();
@endphp
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js', 'version' => $universalFormJsVersion])

<script>
window.UserFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[UserForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    const renderer = new UniversalFormRenderer({
        schema: window.UserFormPage.formSchema,
        config: {},
        formId: 'userForm',
        fieldsWrapperSelector: '#userFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'userFormLoading'
    });
});
</script>
@endpush

