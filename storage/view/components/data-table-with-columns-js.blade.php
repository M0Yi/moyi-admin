{{--
数据表格组件 JS 脚本

使用方式：
@include('components.data-table-with-columns-js')
--}}
@php
    $resourcePath = "/js/components/data-table-with-columns.js";
    $version = $version ?? (defined('APP_VERSION') ? APP_VERSION : '') ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>

