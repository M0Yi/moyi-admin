{{--
Flatpickr 中文语言包组件

LIB=flatpickr VERSION=4.6.13 && \
npm pack ${LIB}@${VERSION} && \
tar -xzf ${LIB}-${VERSION}.tgz && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
cp -r package/dist/* public/npm/${LIB}@${VERSION}/dist && \
rm -rf package ${LIB}-${VERSION}.tgz

版本：4.6.13
使用方式：@include('components.plugin.flatpickr-zh')
--}}


@php
    $version = '4.6.13';
    $resourcePath = "/npm/flatpickr@{$version}/dist/l10n/zh.js";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $href }}"></script>


