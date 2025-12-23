{{--
CRUD generator JS 脚本复用组件
使用方式：
@include('components.crud-config-js')
--}}
@php
    $resourcePath = "/js/admin/system/crud-generator/config.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>


