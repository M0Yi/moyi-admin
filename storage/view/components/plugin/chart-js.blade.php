{{--
Chart.js JavaScript 组件

LIB=chart.js VERSION=4.4.0 && \
npm pack ${LIB}@${VERSION} && \
tar -xzf ${LIB}-${VERSION}.tgz && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
cp -r package/dist/* public/npm/${LIB}@${VERSION}/dist && \
rm -rf package ${LIB}-${VERSION}.tgz

版本：4.4.0
使用方式：@include('components.plugin.chart-js')
--}}

@php
    $version = '4.4.0';
    $resourcePath = "/npm/chart.js@{$version}/dist/chart.umd.js";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp


<script src="{{ $href }}"></script>

