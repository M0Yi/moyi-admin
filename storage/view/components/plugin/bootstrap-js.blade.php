{{--
Bootstrap JavaScript 组件

LIB=bootstrap VERSION=5.3.2 && \
PKG_FILE=$(npm pack ${LIB}@${VERSION}) && \
mkdir -p public/npm/${LIB}@${VERSION} && \
tar -xzf "$PKG_FILE" && \
cp -r package/* public/npm/${LIB}@${VERSION}/ && \
rm -rf package "$PKG_FILE"

版本：5.3.2
使用方式：@include('components.plugin.bootstrap-js')
--}}



@php
    $version = '5.3.2';
    $resourcePath = "/npm/bootstrap@{$version}/dist/js/bootstrap.bundle.min.js";
    $cdn = site()?->resource_cdn;
    $href = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePath
        : $resourcePath;
@endphp

<script src="{{ $href }}"></script>

