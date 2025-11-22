{{--
管理员标签页管理器 JS 脚本

使用方式：
@include('components.admin-tab-manager-js')
--}}
@php
    $resourcePath = "/js/admin-tab-manager.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>

