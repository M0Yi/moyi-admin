{{--
管理后台脚本组件

参数：
- $path: 脚本路径（必需）
- $attributes: 额外的属性数组（可选）

使用方式：
@include('components.admin-script', ['path' => '/js/components/example.js'])

特殊处理：
- 当加载 universal-form-renderer.js 时，自动包含所有表单相关组件
--}}

@php
    $path = $path ?? '';
    $attributes = $attributes ?? [];

    // 当加载 UniversalFormRenderer 时，自动包含所有表单组件
    $isUniversalFormRenderer = str_contains($path, 'universal-form-renderer');

    // 构建属性字符串
    $attrString = '';
    if (!empty($attributes)) {
        $attrPairs = [];
        foreach ($attributes as $key => $value) {
            $attrPairs[] = htmlspecialchars($key, ENT_QUOTES) . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }
        $attrString = ' ' . implode(' ', $attrPairs);
    }
@endphp

@if($isUniversalFormRenderer)
    {{-- 引入通用表单组件包 --}}
    @include('components.universal-form-components', $attributes ?? [])
@endif

{{-- 引入指定的脚本 --}}
<script src="{{ $path }}"{{ $attrString }}></script>