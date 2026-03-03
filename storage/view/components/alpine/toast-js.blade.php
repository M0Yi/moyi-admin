{{--
Toast 组件入口

功能：
- 引入 Toast JS 逻辑
- 提供容器组件

使用方式：
- 在布局中引入：@include('components.alpine.toast-js')
- 在页面中显示容器：@include('components.alpine.toast.container')
- 使用：$toast.success('操作成功');
--}}

@php
    $path = $path ?? '/js/components/toast/index.js';
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

{{-- Toast 逻辑脚本 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
