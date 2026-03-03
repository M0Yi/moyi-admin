{{--
wangEditor JavaScript 组件

LIB=@wangeditor/editor VERSION=5.1.23 && \
npm pack ${LIB}@${VERSION} && \
tar -xzf ${LIB}-${VERSION}.tgz && \
mkdir -p public/npm/${LIB}@${VERSION}/dist && \
cp -r package/dist/* public/npm/${LIB}@${VERSION}/dist && \
rm -rf package ${LIB}-${VERSION}.tgz

版本：5.1.23
使用方式：@include('components.plugin.wang-editor-js')
--}}

@php
    $version = '5.1.23';
    $resourcePath = "/npm/@wangeditor/editor@{$version}/dist/index.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>
