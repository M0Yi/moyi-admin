{{--
Alpine.js 组件

LIB=alpinejs VERSION=3.13.3 && \
PKG_FILE=$(npm pack npmjs.com/package/${LIB}@${VERSION}) && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
tar -xzf "$PKG_FILE" && \
cp -r package/* public/npm/${LIB}@${VERSION}/ && \
rm -rf package "$PKG_FILE"

版本：3.13.3
使用方式：@include('components.plugin.alpinejs')
--}}

@php
    $version = '3.13.3';
    $resourcePath = "/npm/alpinejs@{$version}/dist/cdn.min.js";
    $cdn = site()?->resource_cdn;
    $localPath = $resourcePath;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $localPath
        : $localPath;
@endphp

{{-- Alpine.js：轻量级 JavaScript 框架，用于增强 Blade 模板交互能力 --}}
<script defer src="{{ $href }}"></script>
