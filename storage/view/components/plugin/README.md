# 外部资源组件目录

本目录包含所有外部第三方资源的独立组件文件，每个组件都有独立的版本管理。

## 目录结构

```
components/vendor/
├── bootstrap-css.blade.php      # Bootstrap CSS (版本: 5.3.2)
├── bootstrap-js.blade.php       # Bootstrap JavaScript (版本: 5.3.2)
├── bootstrap-icons.blade.php    # Bootstrap Icons (版本: 1.11.3)
├── tom-select-css.blade.php    # Tom Select CSS (版本: 2.3.1)
├── tom-select-js.blade.php     # Tom Select JavaScript (版本: 2.3.1)
├── flatpickr-css.blade.php     # Flatpickr CSS (版本: latest)
├── flatpickr-js.blade.php      # Flatpickr JavaScript (版本: latest)
└── flatpickr-zh.blade.php       # Flatpickr 中文语言包 (版本: latest)
```

## 使用方式

### 按需引入

在布局文件或页面中，按需引入需要的组件：

```blade
{{-- 引入 Bootstrap CSS --}}
@include('components.plugin.bootstrap-css')

{{-- 引入 Bootstrap Icons --}}
@include('components.plugin.bootstrap-icons')

{{-- 引入 Bootstrap JavaScript --}}
@include('components.plugin.bootstrap-js')

{{-- 引入 Tom Select CSS --}}
@include('components.plugin.tom-select-css')

{{-- 引入 Tom Select JavaScript --}}
@include('components.plugin.tom-select-js')

{{-- 引入 Flatpickr CSS --}}
@include('components.plugin.flatpickr-css')

{{-- 引入 Flatpickr JavaScript --}}
@include('components.plugin.flatpickr-js')

{{-- 引入 Flatpickr 中文语言包 --}}
@include('components.plugin.flatpickr-zh')
```

## 版本管理

每个组件文件内部都定义了版本号，修改版本只需编辑对应的组件文件：

```blade
{{-- bootstrap-css.blade.php --}}
@php
    $version = '5.3.2';  // 修改这里即可更新版本
    // ...
@endphp
```

## 本地文件优先

所有组件都支持本地文件优先策略：

1. 优先检查本地文件是否存在（`/public/vendor/` 目录）
2. 如果本地文件存在，使用本地文件
3. 如果本地文件不存在，自动回退到 CDN

## 组件说明

### Bootstrap

- **CSS**: `bootstrap-css.blade.php` - Bootstrap 5.3.2 样式文件
- **JS**: `bootstrap-js.blade.php` - Bootstrap 5.3.2 JavaScript 文件（包含 Popper.js）
- **Icons**: `bootstrap-icons.blade.php` - Bootstrap Icons 1.11.3 图标字体

### Tom Select

- **CSS**: `tom-select-css.blade.php` - Tom Select 2.3.1 样式文件（Bootstrap 5 兼容版）
- **JS**: `tom-select-js.blade.php` - Tom Select 2.3.1 JavaScript 文件

### Flatpickr

- **CSS**: `flatpickr-css.blade.php` - Flatpickr 日期选择器样式
- **JS**: `flatpickr-js.blade.php` - Flatpickr 日期选择器 JavaScript
- **中文包**: `flatpickr-zh.blade.php` - Flatpickr 中文语言包

## 使用示例

### 后台布局（完整功能）

```blade
<head>
    {{-- CSS 资源 --}}
    @include('components.plugin.bootstrap-css')
    @include('components.plugin.bootstrap-icons')
    @include('components.plugin.tom-select-css')
    @include('components.plugin.flatpickr-css')
</head>
<body>
    {{-- 页面内容 --}}
    
    {{-- JavaScript 资源 --}}
    @include('components.plugin.bootstrap-js')
    @include('components.plugin.tom-select-js')
    @include('components.plugin.flatpickr-js')
    @include('components.plugin.flatpickr-zh')
</body>
```

### 前台布局（基础功能）

```blade
<head>
    {{-- CSS 资源 --}}
    @include('components.plugin.bootstrap-css')
    @include('components.plugin.bootstrap-icons')
</head>
<body>
    {{-- 页面内容 --}}
    
    {{-- JavaScript 资源 --}}
    @include('components.plugin.bootstrap-js')
</body>
```

### 错误页面（仅样式）

```blade
<head>
    {{-- CSS 资源 --}}
    @include('components.plugin.bootstrap-css')
    @include('components.plugin.bootstrap-icons')
</head>
```

## 优势

1. **按需引入**：只引入需要的组件，减少不必要的资源加载
2. **独立版本管理**：每个组件独立管理版本，互不影响
3. **易于维护**：修改版本只需编辑对应的组件文件
4. **本地优先**：自动检测本地文件，提高加载速度
5. **灵活扩展**：可以轻松添加新的组件文件

