@extends('admin.layouts.admin')

@section('title', '编辑菜单')

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">编辑菜单</h6>
        <small class="text-muted">修改菜单信息</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 text-muted mb-3" id="menuFormLoading">
                <div class="spinner-border spinner-border-sm" role="status"></div>
                <span>表单配置加载中，请稍候...</span>
                    </div>
            <form id="menuForm" class="d-none">
                <div class="row" id="menuFormFields"></div>
            </form>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '修改完成后点击保存按钮提交',
    'cancelUrl' => admin_route('system/menus'),
    'submitText' => '保存',
    'formId' => 'menuForm',
    'submitBtnId' => 'submitBtn'
])
@endsection

@push('admin_scripts')
<!-- 引入图标选择器组件 -->
@include('components.icon-picker', ['targetInputId' => 'icon'])

<!-- 引入通用表单渲染器 -->
@php
    $universalFormJsVersion = file_exists(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        ? filemtime(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        : time();
@endphp
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js', 'version' => $universalFormJsVersion])

<!-- 菜单表单特殊逻辑 -->
@include('components.admin-script', ['path' => '/js/admin/system/menu-form.js'])

<script>
window.MenuFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[MenuForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    // 初始化通用表单渲染器
    const renderer = new UniversalFormRenderer({
        schema: window.MenuFormPage.formSchema,
        config: {},
        formId: 'menuForm',
        fieldsWrapperSelector: '#menuFormFields',
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'menuFormLoading'
    });
    
    // 初始化菜单表单特殊逻辑（类型切换、路径字段处理等）
    if (window.MenuForm && window.MenuForm.init) {
        window.MenuForm.init({
            isEditMode: true,
            clearHiddenFields: false,     // 编辑模式：不清空已有值，只隐藏字段
            autoSetTargetBlank: false     // 编辑模式：不自动修改已有设置
        });
    }
    
    // 处理表单提交时的路径字段合并
    const form = document.getElementById('menuForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            // 在提交前处理路径字段
            const type = document.getElementById('type')?.value || 'menu';
            const pathInput = document.getElementById('path');
            const linkPathInput = document.getElementById('linkPath');
            
            if (type === 'link' && linkPathInput && linkPathInput.value) {
                // 外链类型：使用 linkPath 的值
                if (pathInput) {
                    pathInput.value = linkPathInput.value;
                }
            } else if (type !== 'link' && pathInput && pathInput.value) {
                // 非外链类型：使用 path 的值
                if (linkPathInput) {
                    linkPathInput.value = '';
                }
            }
    });
}
});
</script>
@endpush
