{{--
Addon Detail JS 脚本组件

使用方式：
@include('components.addon.addon-detail-js')
--}}
@php
    $resourcePath = "/js/admin/system/addon-detail.js";
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>


