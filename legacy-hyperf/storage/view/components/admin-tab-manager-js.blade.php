{{--
管理员标签页管理器 JS 脚本

使用方式：
@include('components.admin-tab-manager-js')
--}}
@php
    $resourcePath = "/js/admin-tab-manager.js";
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

