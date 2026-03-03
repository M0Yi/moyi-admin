{{--
TinyMCE 富文本编辑器 JavaScript 组件

下载命令:
cd /Users/moyi/moyi-admin && \
npm pack tinymce@8.3.2 && \
tar -xzf tinymce-8.3.2.tgz && \
mkdir -p public/npm/tinymce@8.3.2 && \
cp -r package/* public/npm/tinymce@8.3.2/ && \
rm -rf package tinymce-8.3.2.tgz

版本：8.3.2
使用方式：@include('components.plugin.tinymce-js')
--}}

@php
    $version = '8.3.2';
    $resourcePath = "/npm/tinymce@{$version}/tinymce.min.js";
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $src }}"></script>
