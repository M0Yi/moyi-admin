{{--
Sortable.js JavaScript 组件

-- npm 下载
LIB=sortablejs VERSION=1.15.0 && \
PKG_FILE=$(npm pack ${LIB}@${VERSION}) && \
mkdir -p public/npm/${LIB}@${VERSION} && \
tar -xzf "$PKG_FILE" && \
cp -r package/* public/npm/${LIB}@${VERSION}/ && \
rm -rf package "$PKG_FILE"

版本：1.15.0
使用方式：@include('components.plugin.sortable-js')
--}}

@php
    $version = '1.15.0';
    $resourcePath = "/npm/sortablejs@{$version}/Sortable.min.js";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $href }}"></script>

