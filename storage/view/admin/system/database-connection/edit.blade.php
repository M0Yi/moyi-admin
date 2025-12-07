@extends('admin.layouts.admin')

@section('title', '编辑数据库连接')

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
        <h6 class="mb-1 fw-bold">编辑数据库连接</h6>
        <small class="text-muted">修改数据库连接配置</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="databaseConnectionFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
            </div>
            <form id="databaseConnectionForm" class="d-none">
                <div class="row" id="databaseConnectionFormFields"></div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'infoText' => '修改完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/database-connections'),
    'submitText' => '保存',
    'formId' => 'databaseConnectionForm',
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
window.DatabaseConnectionFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[DatabaseConnectionForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    const renderer = new UniversalFormRenderer({
        schema: window.DatabaseConnectionFormPage.formSchema,
        config: {},
        formId: 'databaseConnectionForm',
        fieldsWrapperSelector: '#databaseConnectionFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'databaseConnectionFormLoading'
    });
});
</script>
@endpush



