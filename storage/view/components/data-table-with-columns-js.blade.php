{{--
数据表格组件 JS 脚本

使用方式：
@include('components.data-table-with-columns-js')
--}}
@php
    $resourcePath = "/js/components/data-table-with-columns.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>

