{{--
CRUD generator JS 脚本复用组件
使用方式：
@include('components.crud-config-js')
--}}
@php
    $resourcePath = "/js/admin/system/crud-generator/config.js";
    // 版本优先：传入的 $version -> APP_VERSION 常量
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>


