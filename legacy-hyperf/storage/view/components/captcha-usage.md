# 验证码组件使用文档

## 概述

验证码组件已经重构为可复用的组件形式，可以在任何需要验证码的场景中使用。

**验证码特性：**
- **随机类型**：验证码类型由后端随机选择（字符验证码或数学验证码），前端无需指定
- **统一格式**：所有验证码统一以图片形式返回和显示
- **字符验证码**：显示数字和字母组合（如 "A3B7"），用户输入图片中的字符
- **数学验证码**：显示加减法数学题（如 "3 + 5 = ?"），用户输入计算结果（如 "8"）

## 组件结构

### 1. 后端服务
- `App\Service\CaptchaService` - 验证码服务（生成和验证验证码）
- `App\Controller\CaptchaController` - 验证码控制器（提供获取验证码图片的接口）

### 2. 中间件
- `App\Middleware\VerifyCaptchaMiddleware` - 通用验证码验证中间件（默认始终验证验证码）
- `App\Middleware\AlwaysCaptchaMiddleware` - 始终需要验证码的中间件（语义更明确，适用于前端登录等场景）
- `App\Middleware\ConditionalCaptchaMiddleware` - 条件验证码中间件（基于登录失败逻辑，支持免验证码令牌）
- `App\Middleware\LoginCaptchaMiddleware` - 登录专用验证码中间件（与 ConditionalCaptchaMiddleware 功能相同，命名更明确）

### 3. 前端组件
- `storage/view/components/captcha.blade.php` - Blade 验证码组件

## 使用方式

### 方式一：简单场景（始终需要验证码）

适用于注册、找回密码、重置密码、前端登录等场景，始终需要验证码。

#### 1. 在 Blade 模板中使用组件

**基础用法（验证码类型由后端随机选择）：**

```blade
@include('components.captcha', [
    'name' => 'captcha',
    'id' => 'captcha',
    'label' => '验证码',
    'placeholder' => '请输入验证码',
    'required' => true,
    'captchaUrl' => '/captcha',  // 通用验证码接口，不在管理后台路径下
])
```

**说明：**
- 验证码类型由后端随机选择（字符验证码或数学验证码）
- 所有验证码统一以图片形式显示
- 如果是字符验证码，用户输入图片中的字母和数字
- 如果是数学验证码，用户输入计算结果（如 "3 + 5 = ?" 应输入 "8"）

#### 2. 在路由中应用中间件

**方式 A：使用 VerifyCaptchaMiddleware（通用）**

```php
// config/routes.php
Router::post('/register', 'App\Controller\AuthController@register', [
    'middleware' => [
        \App\Middleware\VerifyCaptchaMiddleware::class,
    ]
]);
```

**方式 B：使用 AlwaysCaptchaMiddleware（语义更明确，推荐用于前端登录）**

```php
// config/routes.php
// 前端登录系统，需要始终保持验证码
Router::post('/frontend/login', 'App\Controller\Frontend\AuthController@login', [
    'middleware' => [
        \App\Middleware\AlwaysCaptchaMiddleware::class,
    ]
]);

// 注册、找回密码等场景
Router::post('/register', 'App\Controller\AuthController@register', [
    'middleware' => [
        \App\Middleware\AlwaysCaptchaMiddleware::class,
    ]
]);
```

**两种方式的区别：**
- `VerifyCaptchaMiddleware`：通用中间件，默认始终验证验证码
- `AlwaysCaptchaMiddleware`：继承自 `VerifyCaptchaMiddleware`，语义更明确，专门用于始终需要验证码的场景
- 功能完全相同，选择哪个主要看代码可读性

### 方式二：登录场景（根据失败次数决定是否需要验证码）

适用于登录场景，第一个窗口可以免验证码，后续窗口或失败后需要验证码。

**工作原理：**
1. 第一个窗口：可以获取免验证码令牌，无需输入验证码即可提交
2. 后续窗口：无法获取令牌，需要输入验证码
3. 登录失败后：清除令牌，后续请求必须输入验证码
4. 基于 IP 地址记录失败次数，防止暴力破解

#### 1. 在 Blade 模板中使用组件

```blade
@php
    // 动态构建检查验证码的 URL
    // 注意：在控制器中获取路径并传递给视图，不要使用 request() 函数
    $checkUrl = $checkCaptchaUrl ?? '/admin/login/check-captcha';
@endphp

@include('components.captcha', [
    'name' => 'captcha',
    'id' => 'captcha',
    'label' => '验证码',
    'placeholder' => '请输入验证码',
    'required' => false,
    'captchaUrl' => '/captcha',  // 通用验证码接口，不在管理后台路径下
    'checkUrl' => $checkUrl,
    'showFreeToken' => true,
])
```

#### 2. 在路由中应用中间件

**方式 A：使用 ConditionalCaptchaMiddleware（通用命名，推荐）**

```php
// config/routes.php
Router::post('/login', 'App\Controller\AuthController@login', [
    'middleware' => [
        \App\Middleware\ConditionalCaptchaMiddleware::class,
    ]
]);
```

**方式 B：使用 LoginCaptchaMiddleware（登录专用命名）**

```php
// config/routes.php
Router::post('/login', 'App\Controller\AuthController@login', [
    'middleware' => [
        \App\Middleware\LoginCaptchaMiddleware::class,
    ]
]);
```

**两种方式的区别：**
- `ConditionalCaptchaMiddleware`：通用命名，适用于任何需要基于失败次数决定是否需要验证码的场景
- `LoginCaptchaMiddleware`：登录专用命名，语义更明确，专门用于登录场景
- 功能完全相同，选择哪个主要看代码可读性

#### 3. 创建检查验证码需求的接口（可选，已废弃）

**注意**：此接口已不再需要。现在可以直接通过 `/captcha` 接口获取 `free_token` 来判断是否需要验证码：
- 如果 `free_token` 存在（不为 null），表示不需要验证码
- 如果 `free_token` 为 null，表示需要验证码

如果仍需要此接口（用于向后兼容），可以这样实现：

```php
// App\Controller\AuthController.php
public function checkCaptcha(): ResponseInterface
{
    $loginAttemptService = make(LoginAttemptService::class);
    
    // 尝试获取免验证码令牌
    $freeToken = $loginAttemptService->tryGetFreeToken();
    
    // 直接返回 free_token，前端通过 free_token 是否存在来判断是否需要验证码
    return $this->response->json([
        'code' => 200,
        'data' => [
            'free_token' => $freeToken,  // 为 null 时表示需要验证码，不为 null 时表示不需要验证码
        ],
    ]);
}
```

### 方式三：自定义验证码字段名

如果表单中的验证码字段名不是 `captcha`，可以自定义：

```php
// 在路由中配置中间件时传入参数
Router::post('/custom-action', 'App\Controller\CustomController@action', [
    'middleware' => [
        \App\Middleware\VerifyCaptchaMiddleware::class . ':captcha_code,verification_code',
    ]
]);
```

注意：Hyperf 中间件不支持直接传参，需要通过依赖注入容器配置。

## 中间件对比

| 中间件 | 验证模式 | 免验证码令牌 | 使用场景 | 推荐场景 |
|--------|---------|------------|---------|---------|
| `AlwaysCaptchaMiddleware` | 始终验证验证码 | ❌ 不支持 | 前端登录、注册、找回密码等 | 前端登录系统（始终保持验证码） |
| `VerifyCaptchaMiddleware` | 始终验证验证码 | ❌ 不支持 | 注册、找回密码等通用场景 | 通用场景（默认始终验证） |
| `ConditionalCaptchaMiddleware` | 根据失败次数决定 | ✅ 支持 | 登录场景（第一个窗口免验证码） | 登录场景（通用命名） |
| `LoginCaptchaMiddleware` | 根据失败次数决定 | ✅ 支持 | 登录场景（第一个窗口免验证码） | 登录场景（专用命名） |

**选择建议：**
- **始终需要验证码**：使用 `AlwaysCaptchaMiddleware`（语义更明确）或 `VerifyCaptchaMiddleware`（通用）
- **根据失败次数决定**：使用 `ConditionalCaptchaMiddleware`（通用命名）或 `LoginCaptchaMiddleware`（登录专用）

## 组件参数说明

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `name` | string | `captcha` | 输入框 name 属性 |
| `id` | string | `captcha` | 输入框 id 属性 |
| `label` | string | `验证码` | 标签文本 |
| `placeholder` | string | `请输入验证码` | 占位符 |
| `required` | bool | `false` | 是否必填 |
| `captchaUrl` | string | `/captcha` | 获取验证码的 URL（通用接口，不在管理后台路径下，前端登录也可以使用） |
| `showFreeToken` | bool | `false` | 是否支持免验证码令牌（用于登录场景） |

**注意：**
- 验证码类型由后端随机选择（字符验证码或数学验证码），前端无需指定
- 所有验证码统一以图片形式返回和显示
- 字符验证码：显示字母和数字组合（如 "A3B7"）
- 数学验证码：显示数学题（如 "3 + 5 = ?"），用户需要输入计算结果

## JavaScript API

组件会暴露以下全局函数供外部调用：

- `window.refreshCaptcha_{id}()` - 刷新验证码
- `window.checkCaptchaRequirement_{id}()` - 检查是否需要验证码（需要 checkUrl）
- `window.showCaptchaGroup_{id}()` - 显示验证码输入框
- `window.hideCaptchaGroup_{id}()` - 隐藏验证码输入框
- `window.getFreeToken_{id}()` - 获取免验证码令牌（需要 showFreeToken=true）

示例：

```javascript
// 刷新验证码
window.refreshCaptcha_captcha();

// 获取免验证码令牌（登录场景）
const freeToken = window.getFreeToken_captcha();
if (freeToken) {
    requestData.free_token = freeToken;
}
```

## 样式说明

验证码组件使用以下 CSS 类：

- `.captcha-group` - 验证码容器
- `.captcha-input` - 验证码输入框
- `.captcha-image-wrapper` - 验证码图片容器
- `.captcha-image` - 验证码图片（统一格式，无论是字符验证码还是数学验证码都显示为图片）
- `.captcha-refresh` - 刷新按钮

如果页面中没有这些样式，需要添加相应的 CSS。可以参考 `storage/view/admin/auth/login.blade.php` 中的样式。

**注意：** 所有验证码统一以图片形式显示，样式一致。

## 示例场景

### 1. 注册页面

```blade
{{-- 注册表单 --}}
<form id="registerForm" onsubmit="return handleRegister(event)">
    <input type="text" name="username" required>
    <input type="password" name="password" required>
    
    {{-- 验证码组件（类型由后端随机选择） --}}
    @include('components.captcha', [
        'name' => 'captcha',
        'id' => 'registerCaptcha',
        'label' => '验证码',
        'placeholder' => '请输入验证码',
        'required' => true,
    ])
    
    <button type="submit">注册</button>
</form>
```

**说明：**
- 验证码类型由后端随机选择（字符验证码或数学验证码）
- 所有验证码统一以图片形式显示
- 如果是字符验证码，用户输入图片中的字母和数字
- 如果是数学验证码，用户输入计算结果（如 "3 + 5 = ?" 应输入 "8"）

```php
// config/routes.php
Router::post('/register', 'App\Controller\AuthController@register', [
    'middleware' => [
        \App\Middleware\VerifyCaptchaMiddleware::class,
    ]
]);
```

### 2. 找回密码页面

```blade
{{-- 找回密码表单 --}}
<form id="forgotPasswordForm" onsubmit="return handleForgotPassword(event)">
    <input type="email" name="email" required>
    
    {{-- 验证码组件（类型由后端随机选择） --}}
    @include('components.captcha', [
        'name' => 'captcha',
        'id' => 'forgotPasswordCaptcha',
        'required' => true,
    ])
    
    <button type="submit">发送重置链接</button>
</form>
```

```php
// config/routes.php
Router::post('/forgot-password', 'App\Controller\AuthController@forgotPassword', [
    'middleware' => [
        \App\Middleware\AlwaysCaptchaMiddleware::class,
    ]
]);
```

### 3. 前端登录页面（始终保持验证码）

```blade
{{-- 前端登录表单 --}}
<form id="frontendLoginForm" onsubmit="return handleFrontendLogin(event)">
    <input type="text" name="username" required>
    <input type="password" name="password" required>
    
    {{-- 验证码组件（始终显示，类型由后端随机选择） --}}
    @include('components.captcha', [
        'name' => 'captcha',
        'id' => 'frontendLoginCaptcha',
        'required' => true,
        'captchaUrl' => '/captcha',
    ])
    
    <button type="submit">登录</button>
</form>
```

```php
// config/routes.php
// 前端登录系统，需要始终保持验证码
Router::post('/frontend/login', 'App\Controller\Frontend\AuthController@login', [
    'middleware' => [
        \App\Middleware\AlwaysCaptchaMiddleware::class,
    ]
]);
```

## 注意事项

1. **验证码服务使用 Session 存储验证码**，确保 Session 已正确配置
2. **验证码过期时间为 5 分钟**，可以在 `CaptchaService` 中修改
3. **验证码验证成功后会自动清除**，防止重复使用
4. **登录场景的免验证码令牌基于 IP**，同一 IP 的第一个窗口可以免验证码
5. **组件支持多个验证码实例**，通过不同的 `id` 区分

## 迁移指南

如果之前直接使用验证码相关代码，可以按以下步骤迁移：

1. 将验证码 HTML 替换为组件调用
2. 移除自定义的验证码 JavaScript 代码
3. 在路由中应用相应的中间件
4. 测试验证码功能是否正常

