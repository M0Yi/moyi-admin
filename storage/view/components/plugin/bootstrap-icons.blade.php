{{--
Bootstrap Icons 组件


LIB=bootstrap-icons VERSION=1.13.1 && \
PKG_FILE=$(npm pack ${LIB}@${VERSION}) && \
mkdir -p public/npm/${LIB}@${VERSION} && \
tar -xzf "$PKG_FILE" && \
cp -r package/* public/npm/${LIB}@${VERSION}/ && \
rm -rf package "$PKG_FILE"

版本：1.11.3
使用方式：@include('components.plugin.bootstrap-icons')
--}}

@php
    $version = '1.13.1';
    $resourcePath = "/npm/bootstrap-icons@{$version}/font/bootstrap-icons.min.css";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<link rel="stylesheet" href="{{ $href }}">

