{{--
Modal 弹窗组件入口

功能：
- 引入 Modal JS 逻辑
- 自动注入 DOM 元素

使用方式：
- 在布局中引入：@include('components.alpine.modal-js')
- 使用：$modal.alert('提示信息');
--}}

@php
    $path = $path ?? '/js/components/modal/index.js';
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

{{-- Modal 逻辑脚本 --}}
<script src="{{ '/' . ltrim($path, '/') }}"{{ $attrString }}></script>
