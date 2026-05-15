{{--
TinyMCE 富文本编辑器 CSS 组件

TinyMCE 8.x 不需要额外引入 CSS 文件。
样式通过 JS 初始化时自动加载 skins 目录中的样式文件。

如果需要自定义样式，可以在 JS 配置中覆盖：
- skin_url: 主题皮肤路径
- content_css: 内容区域样式

使用方式：@include('components.plugin.tinymce-css')

注意：TinyMCE JS 文件已包含所有必要的样式，
无需在页面中额外引入 CSS。
--}}
