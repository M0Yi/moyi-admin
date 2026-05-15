{{--
Confirm 确认对话框组件入口

功能：
- 引入 Confirm JS 逻辑
- 自动注入 DOM 元素

使用方式：
- 在布局中引入：@include('components.alpine.confirm-js')
- 使用：await $confirm.danger('确定要删除吗？');
--}}

@php
    $path = $path ?? '/js/components/confirm/index.js';
    $attributes = $attributes ?? [];

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

{{-- Confirm 逻辑脚本 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
