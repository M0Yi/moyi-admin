{{--
管理员样式

使用方式：
@include('components.admin-style')
--}}
@php
    $resourcePath = "/css/admin_style.css";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<link href="{{ $href }}" rel="stylesheet">

