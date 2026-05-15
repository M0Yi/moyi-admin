{{--
TinyMCE 多语言包

下载命令:
cd /Users/moyi/moyi-admin && \
npm pack tinymce-i18n@26.2.16 && \
tar -xzf tinymce-i18n-26.2.16.tgz && \
mkdir -p public/npm/tinymce-i18n@26.2.16 && \
cp -r package/* public/npm/tinymce-i18n@26.2.16/ && \
rm -rf package tinymce-i18n-26.2.16.tgz

版本：26.2.16 (日期格式)
使用方式：@include('components.plugin.tinymce-i18n-js')
--}}

@php
    $version = '26.2.16';
    // TinyMCE 8.x 使用 langs8 目录
    $resourcePath = "/npm/tinymce-i18n@{$version}/langs8";
    $cdn = site()?->resource_cdn;
    $baseUrl = !empty($cdn) ? rtrim($cdn, '/') : '';
@endphp

<script>
    // TinyMCE 语言包路径配置
    window.tinymceLanguagePath = '{{ $baseUrl }}{{ $resourcePath }}';
    window.tinymceDefaultLanguage = 'zh-CN'; // 默认语言
</script>
