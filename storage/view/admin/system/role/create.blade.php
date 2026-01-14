@extends('admin.layouts.admin')

@section('title', '新增角色')

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
        <h6 class="mb-1 fw-bold">新增角色</h6>
        <small class="text-muted">创建新的系统角色</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="roleFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
            </div>
            <form id="roleForm" class="d-none">
                <div class="row" id="roleFormFields"></div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/roles'),
    'submitText' => '保存',
    'formId' => 'roleForm',
    'submitBtnId' => 'submitBtn'
])
@endsection

@push('admin_scripts')
@php
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js'])
@include('components.admin-script', ['path' => '/js/admin/system/role-form.js'])

<script>
window.RoleFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[RoleForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    const renderer = new UniversalFormRenderer({
        schema: window.RoleFormPage.formSchema,
        config: {},
        formId: 'roleForm',
        fieldsWrapperSelector: '#roleFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'roleFormLoading'
    });

    if (window.RoleForm && typeof window.RoleForm.init === 'function') {
        window.RoleForm.init();
    }
});
</script>
@endpush

