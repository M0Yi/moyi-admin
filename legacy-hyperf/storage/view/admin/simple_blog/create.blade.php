@extends('admin.layouts.admin')

@section('title', '创建文章')

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
        <h6 class="mb-1 fw-bold">创建文章</h6>
        <small class="text-muted">撰写新的文章</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="simpleBlogFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
            </div>
            <form id="simpleBlogForm" class="d-none">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div class="row" id="simpleBlogFormFields"></div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route('simple_blog'),
    'submitText' => '保存文章',
    'formId' => 'simpleBlogForm',
    'submitBtnId' => 'submitBtn'
])
@endsection

@push('admin_scripts')
{{-- wangEditor CSS --}}
@include('components.plugin.wang-editor-css')
{{-- wangEditor JS --}}
@include('components.plugin.wang-editor-js')
{{-- Universal Form Renderer --}}
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js'])

<script>
window.SimpleBlogFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[SimpleBlogForm] UniversalFormRenderer 未正确加载');
        return;
    }

    const renderer = new UniversalFormRenderer({
        schema: window.SimpleBlogFormPage.formSchema,
        config: {},
        formId: 'simpleBlogForm',
        fieldsWrapperSelector: '#simpleBlogFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'simpleBlogFormLoading'
    });
});
</script>
@endpush
