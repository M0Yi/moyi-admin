{{--
表单列宽可视化编辑器组件

使用方式：
@include('components.form-col-visualizer-js')

说明：
这是一个独立的 JS 类库，提供表单列宽可视化编辑功能。
使用 FormColVisualizer 类来创建可视化编辑器实例。

示例：
const visualizer = new FormColVisualizer({
    container: '#colVisualizerContent',
    fields: fieldsData,
    onUpdate: (fieldName, colValue) => {
        // 更新表单中的 col 值
    }
});
visualizer.render();
--}}

{{-- 样式：推送到 admin-styles 堆栈（输出到 head） --}}
@push('admin-styles')
<style>
/* 表单列宽可视化编辑器样式 */
.form-col-visualizer {
    padding: 1rem 0;
}

.visualizer-toolbar {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 0.375rem;
}

.visualizer-preview {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 1.5rem;
    background: #fff;
    min-height: 300px;
}

.preview-container {
    position: relative;
}

.preview-form {
    margin: 0;
}

.preview-field {
    transition: all 0.3s ease;
    margin-bottom: 1rem;
}

.preview-field-inner {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem;
    background: #f8f9fa;
    position: relative;
}

.preview-field-inner:hover {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.1);
}

.preview-label {
    display: block;
    font-weight: 500;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    color: #495057;
}

.preview-input {
    margin-bottom: 0.5rem;
}

.preview-input .form-control,
.preview-input .form-select {
    pointer-events: none;
    background: #fff;
}

.preview-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
}

.visualizer-fields {
    margin-top: 2rem;
}

.field-item {
    transition: all 0.2s ease;
}

.field-item:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.field-info {
    min-width: 200px;
}

.field-col-selector {
    min-width: 400px;
    text-align: right;
}

.field-col-selector .btn-group {
    flex-wrap: wrap;
}

.field-col-selector .btn {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    white-space: nowrap;
}

.custom-col-input {
    max-width: 300px;
    margin-left: auto;
}

/* 预览字段下拉框样式 */
.preview-col-selector {
    margin-top: 0.5rem;
}

.preview-col-select {
    width: 100%;
}

.preview-custom-input {
    margin-top: 0.5rem;
}
</style>
@endpush

{{-- JS 脚本：直接输出到当前 @include 位置 --}}
@php
    $resourcePath = "/js/components/form-col-visualizer.js";
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

