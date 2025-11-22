{{--
管理员 JS 脚本

使用方式：
@include('components.admin-js')
--}}
@php
    $resourcePath = "/js/admin.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>

