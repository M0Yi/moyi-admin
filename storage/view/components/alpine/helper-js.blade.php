{{--
通用工具函数组件

功能：
- 常用验证函数（手机、邮箱、URL 等）
- 字符串处理
- 日期处理
- 浏览器检测
- 本地存储封装
- DOM 操作辅助

使用方式：
@include('components.alpine.helper-js')

依赖：
- 无依赖，可独立使用
- 建议在 request-js 之前引入
--}}

@php
    $path = $path ?? '/js/utils/helper.js';
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

{{-- 通用工具函数：提供 $helper 全局对象 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
