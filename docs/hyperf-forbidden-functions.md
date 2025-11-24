# Hyperf 禁止使用的 Laravel 辅助函数

## 概述

Hyperf 虽然使用了类似 Laravel 的 Eloquent ORM 和 Blade 模板引擎，但很多 Laravel 的辅助函数在 Hyperf 中**不可用**，必须使用替代方案。

## 禁止使用的函数列表

### 1. `asset()` - 资源路径生成

#### ❌ 禁止使用
```blade
{{-- 错误：asset() 函数在 Hyperf 中不存在 --}}
<script src="{{ asset('js/components/refresh-parent-listener.js') }}"></script>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
```

#### ✅ Hyperf 替代方案
```blade
{{-- 正确：使用统一的组件加载（支持 CDN） --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js', 'version' => $version])

{{-- CSS 可以直接使用路径（如果需要 CDN 支持，也可以创建类似组件） --}}
<link rel="stylesheet" href="/css/app.css">
```

**说明**：
- Hyperf 中没有 `asset()` 函数
- **必须使用 `@include('components.admin-script')` 组件加载 JS**，支持 CDN 配置
- 组件会自动处理 `site()->resource_cdn` 配置
- 支持版本号参数用于缓存控制
- CSS 可以直接使用路径，或创建类似的组件

### 2. `csrf_token()` - CSRF Token

#### ❌ 禁止使用
```blade
{{-- 错误：csrf_token() 函数在 Hyperf 中不存在（除非自己实现） --}}
<input type="hidden" name="_token" value="{{ csrf_token() }}">
```

#### ✅ Hyperf 替代方案
```blade
{{-- 方式1：如果实现了 csrf_token() 函数，可以使用 --}}
<input type="hidden" name="_token" value="{{ csrf_token() }}">

{{-- 方式2：从 Session 或 Context 获取 --}}
<input type="hidden" name="_token" value="{{ $csrfToken ?? '' }}">

{{-- 方式3：从 meta 标签获取（如果中间件设置了） --}}
<input type="hidden" name="_token" value="{{ $csrfToken ?? '' }}">
```

**说明**：
- 如果项目实现了 `csrf_token()` 函数（如 `app/Functions.php`），可以使用
- 否则需要通过中间件将 CSRF Token 传递给视图

### 3. `route()` - 路由生成

#### ❌ 禁止使用
```blade
{{-- 错误：route() 函数在 Hyperf 中不存在 --}}
<a href="{{ route('users.index') }}">用户列表</a>
```

#### ✅ Hyperf 替代方案
```blade
{{-- 正确：直接使用路径 --}}
<a href="/admin/users">用户列表</a>

{{-- 或者使用自定义的辅助函数（如果实现了） --}}
<a href="{{ admin_route('users') }}">用户列表</a>
```

**说明**：
- Hyperf 不支持路由命名，需要直接使用路径
- 如果项目实现了自定义路由辅助函数（如 `admin_route()`），可以使用

### 4. `url()` - URL 生成

#### ❌ 禁止使用
```blade
{{-- 错误：url() 函数在 Hyperf 中不存在 --}}
<a href="{{ url('/admin/users') }}">用户列表</a>
```

#### ✅ Hyperf 替代方案
```blade
{{-- 正确：直接使用路径或通过 Request 对象生成 --}}
<a href="/admin/users">用户列表</a>

{{-- 或者手动拼接（如果需要完整 URL） --}}
<a href="{{ $request->getUri()->getScheme() }}://{{ $request->getUri()->getHost() }}/admin/users">用户列表</a>
```

### 5. `old()` - 旧输入

#### ❌ 禁止使用
```blade
{{-- 错误：old() 函数在 Hyperf 中不存在 --}}
<input type="text" name="username" value="{{ old('username') }}">
```

#### ✅ Hyperf 替代方案
```blade
{{-- 正确：从 Session 获取旧输入 --}}
<input type="text" name="username" value="{{ $oldInput['username'] ?? '' }}">

{{-- 或者通过中间件将 oldInput 传递给视图 --}}
<input type="text" name="username" value="{{ $oldInput['username'] ?? $user->username ?? '' }}">
```

### 6. `redirect()` - 重定向

#### ❌ 禁止使用（在 Blade 模板中）
```blade
{{-- 错误：redirect() 不能在模板中使用 --}}
@if($condition)
    {{ redirect('/admin/users') }}
@endif
```

#### ✅ Hyperf 替代方案（在控制器中）
```php
// 在控制器中使用 Response 对象
use Hyperf\HttpServer\Contract\ResponseInterface;

class UserController extends AbstractController
{
    public function __construct(
        protected ResponseInterface $response
    ) {}

    public function store()
    {
        // ...
        return $this->response->redirect('/admin/users');
    }
}
```

### 7. `config()` - 配置访问

#### ❌ 禁止使用（简化版）
```blade
{{-- 错误：Laravel 风格的 config() 在 Hyperf 中不可用 --}}
{{ config('app.name') }}
```

#### ✅ Hyperf 替代方案
```blade
{{-- 方式1：通过控制器传递配置到视图 --}}
{{ $appName }}

{{-- 方式2：使用 Hyperf 的 config 函数（如果可用） --}}
{{ config('app.name') }}

{{-- 方式3：在控制器中获取配置 --}}
<?php
use function Hyperf\Config\config;
$appName = config('app.name');
?>
{{ $appName }}
```

### 8. `collect()` - 集合操作

#### ❌ 禁止使用
```php
// 错误：collect() 辅助函数在 Hyperf 中不存在
$hasDeletedAt = collect($columns)->contains('name', 'deleted_at');
$names = collect($users)->pluck('name')->toArray();
```

#### ✅ Hyperf 替代方案
```php
// 正确：使用原生 PHP 数组函数
$hasDeletedAt = false;
foreach ($columns as $column) {
    if ($column['name'] === 'deleted_at') {
        $hasDeletedAt = true;
        break;
    }
}

// 或使用 array_filter
$hasDeletedAt = !empty(array_filter($columns, fn($col) => $col['name'] === 'deleted_at'));

// pluck 替代
$names = array_column($users, 'name');

// map 替代
$mapped = array_map(fn($user) => $user['name'], $users);

// filter 替代
$filtered = array_filter($users, fn($user) => $user['status'] === 1);
```

## 完整禁止列表

以下函数在 Hyperf 中**不可用**（除非项目自己实现）：

- `asset()` - 资源路径生成
- `route()` - 路由生成
- `url()` - URL 生成
- `old()` - 旧输入
- `redirect()` - 重定向（在模板中）
- `csrf_token()` - CSRF Token（除非自己实现）
- `config()` - 配置访问（Laravel 风格）
- `collect()` - 集合辅助函数
- `now()` - 当前时间（使用 Carbon 或 DateTime）
- `request()` - 请求对象（使用依赖注入）
- `auth()` - 认证（使用依赖注入）
- `session()` - Session（使用依赖注入）
- `public_path()` - 公共目录路径（使用 BASE_PATH）
- `storage_path()` - 存储目录路径（使用 BASE_PATH）
- `base_path()` - 基础路径（使用 BASE_PATH 常量）

## 最佳实践

1. **资源路径**：直接使用相对路径 `/js/app.js`，不要使用 `asset()`
2. **路由**：直接使用路径字符串，不要使用 `route()`
3. **配置**：在控制器中获取配置并传递给视图
4. **集合操作**：使用原生 PHP 数组函数（`array_filter`、`array_map`、`array_column` 等）
5. **依赖注入**：使用依赖注入获取 Request、Session、Auth 等对象

## 检查清单

在编写 Blade 模板时，确保：

- [ ] 不使用 `asset()`，直接使用 `/path/to/file`
- [ ] 不使用 `route()`，直接使用路径字符串
- [ ] 不使用 `old()`，从 `$oldInput` 数组获取
- [ ] 不使用 `csrf_token()`，从 `$csrfToken` 变量获取
- [ ] 不使用 `collect()`，使用原生 PHP 数组函数
- [ ] 不使用 `config()`，从控制器传递配置到视图

## 相关文档

- [Hyperf 官方文档](https://hyperf.wiki)
- [Blade 模板引擎文档](https://hyperf.wiki/3.1/#/zh-cn/view-engine)

