{{--
富文本编辑器组件（基于 wangEditor）

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - placeholder: 占位符
  - rows: 行数（默认 10，用于设置编辑器高度）
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    $fieldId = $field['name'];
    $fieldValue = $value ?? ($field['default'] ?? '');
    $placeholder = $field['placeholder'] ?? '请输入内容...';
    $rows = $field['rows'] ?? 10;
    $required = $field['required'] ?? false;
    
    // 计算编辑器高度（每行约 20px，最小 300px 避免 wangEditor 警告）
    $editorHeight = max($rows * 20, 300);
@endphp

<div class="rich-text-editor-wrapper" data-field-id="{{ $fieldId }}">
    {{-- 隐藏字段，存储HTML内容 --}}
    <input type="hidden" name="{{ $fieldId }}" id="{{ $fieldId }}_input" value="{{ htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8') }}">
    
    {{-- wangEditor 编辑器容器（配置通过 data 属性传递） --}}
    <div id="{{ $fieldId }}_editor" style="min-height: {{ $editorHeight }}px;"></div>
    
    <div class="form-text mt-2">
        <i class="bi bi-info-circle me-1"></i>
        支持富文本编辑，可直接粘贴 Word 文档或图片，也可以使用工具栏上传图片，或从图片库选择已上传的图片
    </div>
</div>

{{-- 引入图片库弹窗组件 --}}
@include('admin.components.image-library-modal')

{{-- 引入外部资源（使用 @push 确保只加载一次） --}}
@push('styles')
{{-- wangEditor CSS（使用 JavaScript 检查确保只加载一次） --}}
<script>
(function() {
    // 检查是否已加载 CSS
    if (window.__wangeditor_css_loaded__) {
        return;
    }
    
    // 标记为已加载
    window.__wangeditor_css_loaded__ = true;
    
    // 动态加载 wangEditor CSS
    const link1 = document.createElement('link');
    link1.rel = 'stylesheet';
    link1.href = '/vendor/wangeditor/style.css';
    document.head.appendChild(link1);
    
    // 动态加载自定义样式
    const link2 = document.createElement('link');
    link2.rel = 'stylesheet';
    link2.href = '/css/components/rich-text-editor.css';
    document.head.appendChild(link2);
})();
</script>
{{-- 动态设置编辑器高度 --}}
<style>
.rich-text-editor-wrapper[data-field-id="{{ $fieldId }}"] .w-e-text-container {
    min-height: {{ $editorHeight }}px;
}
</style>
@endpush

@push('scripts')
{{-- wangEditor JS（使用 JavaScript 检查确保只加载一次） --}}
<script>
(function() {
    // 检查是否已加载 wangEditor
    if (window.__wangeditor_loaded__) {
        return;
    }
    
    // 标记为已加载
    window.__wangeditor_loaded__ = true;
    
    // 动态加载 wangEditor JS
    const script1 = document.createElement('script');
    script1.src = '/vendor/wangeditor/index.js';
    script1.async = false;
    document.head.appendChild(script1);
    
    // 动态加载自定义脚本
    script1.onload = function() {
        const script2 = document.createElement('script');
        script2.src = '/js/components/rich-text-editor.js';
        script2.async = false;
        document.head.appendChild(script2);
    };
})();
</script>
{{-- 初始化当前编辑器实例 --}}
<script>
(function() {
    'use strict';
    
    // 等待 DOM 和脚本加载完成
    function initEditor() {
        // 检查是否已经初始化过（避免重复初始化）
        const editorContainer = document.getElementById('{{ $fieldId }}_editor');
        if (editorContainer && editorContainer._editor) {
            console.warn('Rich text editor already initialized:', '{{ $fieldId }}');
            return;
        }

        if (typeof initRichTextEditor === 'function') {
            // 设置编辑器容器的配置属性
            if (editorContainer) {
                editorContainer.setAttribute('data-placeholder', '{{ $placeholder }}');
                editorContainer.setAttribute('data-required', '{{ $required ? "true" : "false" }}');
                editorContainer.setAttribute('data-field-label', '{{ $field["label"] ?? "此字段" }}');
                
                // 初始化编辑器（只初始化一次）
                initRichTextEditor('{{ $fieldId }}');
            }
        } else {
            // 如果脚本还未加载，延迟重试（最多重试 50 次，避免无限循环）
            if (!window.__initEditorRetryCount) {
                window.__initEditorRetryCount = {};
            }
            const retryKey = '{{ $fieldId }}';
            window.__initEditorRetryCount[retryKey] = (window.__initEditorRetryCount[retryKey] || 0) + 1;
            
            if (window.__initEditorRetryCount[retryKey] < 50) {
                setTimeout(initEditor, 100);
            } else {
                console.error('Failed to initialize rich text editor after 50 retries:', '{{ $fieldId }}');
            }
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEditor);
    } else {
        initEditor();
    }
})();
</script>
@endpush
