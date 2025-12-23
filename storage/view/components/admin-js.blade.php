{{--
管理员 JS 脚本

使用方式：
@include('components.admin-js')
--}}
@php
    $resourcePath = "/js/admin.js";
    // 优先使用传入的版本参数（如果通过 @include 传递），否则使用全局常量 APP_VERSION
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

