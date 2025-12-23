{{--
通用 JS 脚本加载组件（支持 CDN）

使用方式：
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])

参数：
- $path: JS 文件路径（相对于 public 目录，必须以 / 开头）
- $version: 可选，版本号（用于缓存控制）
--}}
@php
    $resourcePath = $path ?? '/js/admin.js';
    // 优先使用传入的版本参数，否则使用全局常量 APP_VERSION
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

