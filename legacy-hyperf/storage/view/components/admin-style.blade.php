{{--
通用样式加载组件（支持 CDN）

参数：
- $path: 样式文件路径（相对于 public 目录，必须以 / 开头）
- $version: 可选，版本号（用于缓存控制）

使用方式：
@include('components.admin-style', ['path' => '/css/admin_style.css'])
@include('components.admin-style', ['path' => '/css/components/some-component.css'])
--}}
@php
    $resourcePath = $path ?? "/css/admin_style.css";
    // 优先使用传入的版本参数，否则使用全局常量 APP_VERSION
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<link href="{{ $href }}" rel="stylesheet">

