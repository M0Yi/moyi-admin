@extends('admin.layouts.admin')

@section('title', '插件配置 - ' . ($addon['name'] ?? ''))

@if (! ($isEmbedded ?? false))
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4" id="addon-config-app">
    <!-- 表单卡片 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-muted mb-3" id="addonConfigLoading">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span>配置表单加载中，请稍候...</span>
                    </div>
                    <form id="addonConfigForm" class="d-none">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <div class="row" id="addonConfigFields">
                            <!-- 表单字段将在这里动态生成 -->
                        </div>
                    </form>

                    {{-- 当没有配置项时显示的提示 --}}
                    <div id="noConfigMessage" class="d-none">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 该插件暂无可配置的选项
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'formId' => 'addonConfigForm',
    'cancelUrl' => admin_route('system/addons'),
    'submitText' => '保存配置',
    'infoText' => '修改完成后点击保存配置按钮提交'
])
@push('admin-styles')
<style>
#addonConfigFields .form-label {
    font-weight: 600;
}

.addon-config-field + .form-text {
    margin-top: 0.35rem;
}
</style>
@endpush

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js'])

<script>
window.AddonConfigPage = {
    config: {!! $configJson ?? '{}' !!},
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    console.log('[AddonConfig] 初始化配置页面');
    console.log('[AddonConfig] config:', window.AddonConfigPage.config);
    console.log('[AddonConfig] formSchema:', window.AddonConfigPage.formSchema);

    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[AddonConfig] UniversalFormRenderer 未正确加载');
        return;
    }

    // 检查是否有配置字段
    const fields = window.AddonConfigPage.formSchema.fields || [];
    const hasFields = Array.isArray(fields) && fields.length > 0;

    console.log('[AddonConfig] 字段数量:', fields.length);

    if (!hasFields) {
        // 没有配置字段，显示提示消息
        console.log('[AddonConfig] 没有配置字段，显示提示消息');
        document.getElementById('addonConfigLoading').classList.add('d-none');
        document.getElementById('noConfigMessage').classList.remove('d-none');
        // 隐藏表单
        document.getElementById('addonConfigForm').style.display = 'none';
        return;
    }

    // 确保 schema 中有 submitUrl（从 endpoints.submit 获取）
    if (!window.AddonConfigPage.formSchema.submitUrl && window.AddonConfigPage.formSchema.endpoints && window.AddonConfigPage.formSchema.endpoints.submit) {
        window.AddonConfigPage.formSchema.submitUrl = window.AddonConfigPage.formSchema.endpoints.submit;
        console.log('[AddonConfig] 设置 submitUrl:', window.AddonConfigPage.formSchema.submitUrl);
    }

    // 有配置字段，初始化表单渲染器
    console.log('[AddonConfig] 初始化表单渲染器');
    new UniversalFormRenderer({
        schema: window.AddonConfigPage.formSchema,
        config: window.AddonConfigPage.config,
        formId: 'addonConfigForm',
        fieldsWrapperSelector: '#addonConfigFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'addonConfigLoading'
    });
});
</script>
@endpush

@section('debug')
{{-- 调试信息 - 总是显示 --}}
<div class="card border-0 shadow-sm mt-3">
    <div class="card-body">
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle"></i> 调试信息</h5>
            @if($addon)
            <ul class="mb-0 mt-2">
                <li>插件ID: {{ $addon['id'] ?? 'N/A' }}</li>
                <li>插件名称: {{ $addon['name'] ?? 'N/A' }}</li>
                <li>插件状态: {{ ($addon['enabled'] ?? false) ? '启用' : '禁用' }}</li>
                <li>插件目录: {{ $addon['directory'] ?? 'N/A' }}</li>
                <li>配置项存在: {{ isset($addon['config']['configs']) ? '是' : '否' }}</li>
                <li>配置项类型: {{ isset($addon['config']['configs']) ? gettype($addon['config']['configs']) : 'N/A' }}</li>
                <li>配置项数量: {{ isset($addon['config']['configs']) && is_array($addon['config']['configs']) ? count($addon['config']['configs']) : 'N/A' }}</li>
                <li>完整配置键: {{ isset($addon['config']) ? implode(', ', array_keys($addon['config'])) : 'N/A' }}</li>
                <li>configJson 变量存在: {{ isset($configJson) ? '是' : '否' }}</li>
                <li>formSchemaJson 变量存在: {{ isset($formSchemaJson) ? '是' : '否' }}</li>
                <li>configJson 长度: {{ isset($configJson) ? strlen($configJson) : 'N/A' }}</li>
                <li>formSchemaJson 长度: {{ isset($formSchemaJson) ? strlen($formSchemaJson) : 'N/A' }}</li>
                @if(isset($formSchemaJson) && strlen($formSchemaJson) > 0)
                <li>formSchemaJson 前200字符: {{ substr($formSchemaJson, 0, 200) . (strlen($formSchemaJson) > 200 ? '...' : '') }}</li>
                @endif
            </ul>
            @else
            <p class="mb-0">插件不存在</p>
            @endif
        </div>
    </div>
</div>
@endsection
