@extends('admin.layouts.admin')

@section('title', '新增 AI Agent')

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
        <h6 class="mb-1 fw-bold">新增 AI Agent</h6>
        <small class="text-muted">创建新的 AI Agent 配置</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="agentFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
            </div>
            <form id="agentForm" class="d-none">
                <div class="row" id="agentFormFields"></div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'infoText' => '填写完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/ai-agents'),
    'submitText' => '保存',
    'formId' => 'agentForm',
    'submitBtnId' => 'submitBtn'
])
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js'])
<script>
window.AiAgentFormPage = {
    formSchema: @json($formSchemaJson)
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[AiAgentForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    var renderer = new UniversalFormRenderer({
        schema: window.AiAgentFormPage.formSchema,
        config: {},
        formId: 'agentForm',
        fieldsWrapperSelector: '#agentFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'agentFormLoading'
    });
});
</script>
@endpush
