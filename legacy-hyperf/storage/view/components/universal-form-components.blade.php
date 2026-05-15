{{--
通用表单组件包

包含 UniversalFormRenderer 所需的所有组件：
- TinyMCE 富文本编辑器 (tinymce)
- 颜色选择器 (color-picker)
- 图标选择器 (icon-picker)
- 渐变选择器 (gradient-picker)

支持的参数：
- $colorPickerParams: 传递给颜色选择器的参数
- $iconPickerParams: 传递给图标选择器的参数
- $gradientPickerParams: 传递给渐变选择器的参数

使用方式：
@include('components.universal-form-components')
@include('components.universal-form-components', [
    'iconPickerParams' => ['targetInputId' => 'icon']
])
--}}

@php
    $colorPickerParams = $colorPickerParams ?? [];
    $iconPickerParams = $iconPickerParams ?? [];
    $gradientPickerParams = $gradientPickerParams ?? [];
@endphp

{{-- TinyMCE 富文本编辑器 --}}
@include('components.plugin.tinymce-js')
@include('components.plugin.tinymce-i18n-js')

{{-- 颜色选择器组件 --}}
@include('components.color-picker', $colorPickerParams)

{{-- 图标选择器组件 --}}
@include('components.icon-picker', $iconPickerParams)

{{-- 渐变选择器组件 --}}
@include('components.gradient-picker', $gradientPickerParams)
