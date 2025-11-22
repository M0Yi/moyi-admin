{{--
Iframe Shell JS 脚本

使用方式：
@include('components.iframe-shell-js')
--}}
@php
    $resourcePath = "/js/components/iframe-shell.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>

