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
    $version = $version ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

