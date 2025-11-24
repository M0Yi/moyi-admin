# JS 加载规范

## 概述

为了统一管理 JS 资源加载，支持 CDN 配置，所有 JS 文件必须使用统一的组件方式加载，禁止直接使用 `<script src>` 标签。

## 组件说明

### `components.admin-script` 组件

**位置**：`storage/view/components/admin-script.blade.php`

**功能**：
- 统一处理 JS 资源路径
- 自动支持 CDN 配置（通过 `site()->resource_cdn`）
- 支持版本号参数用于缓存控制

**使用方式**：
```blade
{{-- 基础用法 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])

{{-- 带版本号（用于缓存控制） --}}
@include('components.admin-script', [
    'path' => '/js/components/universal-form-renderer.js',
    'version' => $universalFormJsVersion
])
```

**参数说明**：
- `path`（必需）：JS 文件路径，相对于 `public` 目录，必须以 `/` 开头
- `version`（可选）：版本号，会自动追加为查询参数 `?v=版本号`

## 禁止的写法

### ❌ 禁止：直接使用 script 标签
```blade
{{-- 错误：直接使用 script 标签 --}}
<script src="/js/components/refresh-parent-listener.js"></script>
<script src="/js/components/universal-form-renderer.js?v={{ $version }}"></script>
```

### ✅ 正确：使用组件加载
```blade
{{-- 正确：使用组件加载 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@include('components.admin-script', [
    'path' => '/js/components/universal-form-renderer.js',
    'version' => $version
])
```

## CDN 支持

组件会自动检测 `site()->resource_cdn` 配置：

- **未配置 CDN**：使用相对路径 `/js/xxx.js`
- **配置了 CDN**：自动拼接 CDN 地址，如 `https://cdn.example.com/js/xxx.js`

**示例**：
```php
// 如果 site()->resource_cdn = 'https://cdn.example.com'
// 组件会自动生成：
// <script src="https://cdn.example.com/js/components/refresh-parent-listener.js"></script>
```

## 版本号处理

当提供 `version` 参数时，组件会自动追加查询参数：

```blade
@include('components.admin-script', [
    'path' => '/js/app.js',
    'version' => '1.0.0'
])
```

**生成结果**：
- 无 CDN：`<script src="/js/app.js?v=1.0.0"></script>`
- 有 CDN：`<script src="https://cdn.example.com/js/app.js?v=1.0.0"></script>`

## 组件实现

```blade
{{-- storage/view/components/admin-script.blade.php --}}
@php
    $resourcePath = $path ?? '/js/admin.js';
    $version = $version ?? '';
    $resourcePathWithVersion = $version ? $resourcePath . '?v=' . $version : $resourcePath;
    $cdn = site()?->resource_cdn;
    $src = !empty($cdn)
        ? rtrim($cdn, '/') . $resourcePathWithVersion
        : $resourcePathWithVersion;
@endphp

<script src="{{ $src }}"></script>
```

## 相关组件

### `components.admin-js` 组件

**位置**：`storage/view/components/admin-js.blade.php`

**用途**：专门用于加载主管理 JS 文件 `/js/admin.js`

**使用方式**：
```blade
@include('components.admin-js')
```

**说明**：这是 `admin-script` 组件的特化版本，固定加载 `/js/admin.js`。

## 迁移指南

### 步骤 1：查找所有直接加载的 JS
```bash
# 查找所有直接使用 script src 的地方
grep -r '<script src="/js/' storage/view/
```

### 步骤 2：替换为组件方式
```blade
{{-- 替换前 --}}
<script src="/js/components/xxx.js"></script>

{{-- 替换后 --}}
@include('components.admin-script', ['path' => '/js/components/xxx.js'])
```

### 步骤 3：处理版本号
```blade
{{-- 替换前 --}}
<script src="/js/components/xxx.js?v={{ $version }}"></script>

{{-- 替换后 --}}
@include('components.admin-script', [
    'path' => '/js/components/xxx.js',
    'version' => $version
])
```

## 检查清单

在编写或修改 Blade 模板时，确保：

- [ ] 不使用 `<script src="/js/...">` 直接加载 JS
- [ ] 使用 `@include('components.admin-script')` 组件加载所有 JS
- [ ] JS 路径参数以 `/` 开头
- [ ] 版本号通过 `version` 参数传递，不要手动拼接
- [ ] 主管理 JS 使用 `@include('components.admin-js')`

## 常见问题

### Q: 为什么不能直接使用 script 标签？
A: 为了统一支持 CDN 配置，如果直接使用 script 标签，CDN 配置无法生效。

### Q: CSS 文件也需要使用组件吗？
A: 目前 CSS 可以直接使用路径，如果需要 CDN 支持，可以创建类似的 `admin-style` 组件。

### Q: 如何获取文件修改时间作为版本号？
A: 在控制器或视图中计算：
```php
@php
    $version = file_exists(BASE_PATH . '/public/js/app.js')
        ? filemtime(BASE_PATH . '/public/js/app.js')
        : time();
@endphp
@include('components.admin-script', [
    'path' => '/js/app.js',
    'version' => $version
])
```

## 相关文档

- [Hyperf 禁止使用的函数](./hyperf-forbidden-functions.md)
- [Blade 模板规范](../.cursorrules#blade-模板和前端规范)

