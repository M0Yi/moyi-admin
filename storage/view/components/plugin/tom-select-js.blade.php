{{--
Tom Select JavaScript 组件

LIB=tom-select VERSION=2.3.1 && \
npm pack ${LIB}@${VERSION} && \
tar -xzf ${LIB}-${VERSION}.tgz && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
cp -r package/dist/* public/npm/${LIB}@${VERSION}/dist && \
rm -rf package ${LIB}-${VERSION}.tgz

版本：2.3.1
使用方式：@include('components.plugin.tom-select-js')
--}}

@php
    $version = '2.3.1';
    $resourcePath = "/npm/tom-select@{$version}/dist/js/tom-select.complete.min.js";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $href }}"></script>
