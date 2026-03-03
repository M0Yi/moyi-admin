{{--
HTTP 请求封装组件

功能：
- 自动携带 CSRF Token
- 自动处理 JWT Token
- 统一错误处理
- 自动显示 Toast 通知
- 统一 Loading 状态

使用方式：
@include('components.alpine.request-js')

依赖：
- 必须先引入 Alpine.js（@include('components.plugin.alpinejs')）
- 需要 Toast 和 Loading 组件支持
--}}

@php
    $path = $path ?? '/js/utils/request.js';
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

{{-- HTTP 请求封装：提供 $http 全局对象用于 AJAX 请求 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
