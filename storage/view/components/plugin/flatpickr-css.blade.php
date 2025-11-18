{{--
Flatpickr CSS 组件

LIB=flatpickr VERSION=4.6.13 && \
npm pack ${LIB}@${VERSION} && \
tar -xzf ${LIB}-${VERSION}.tgz && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
cp -r package/dist/* public/npm/${LIB}@${VERSION}/dist && \
rm -rf package ${LIB}-${VERSION}.tgz

版本：4.6.13
使用方式：@include('components.plugin.flatpickr-css')
--}}


@php
    $version = '4.6.13';
    $resourcePath = "/npm/flatpickr@{$version}/dist/flatpickr.min.css";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<link rel="stylesheet" href="{{ $href }}">

