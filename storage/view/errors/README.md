# 错误页面目录

本目录包含所有 HTTP 错误页面的 Blade 模板。

## 📁 文件列表

| 文件 | HTTP 状态码 | 说明 | 设计风格 |
|------|------------|------|---------|
| `401.blade.php` | 401 Unauthorized | 未授权访问 | 蓝绿渐变 + 钥匙图标 |
| `403.blade.php` | 403 Forbidden | 禁止访问 | 橙粉渐变 + 盾牌锁图标 |
| `404.blade.php` | 404 Not Found | 页面未找到 | 紫色渐变 + 指南针图标 |
| `500.blade.php` | 500 Internal Server Error | 服务器错误 | 粉红渐变 + 警告三角形图标 |
| `503.blade.php` | 503 Service Unavailable | 服务维护中 | 浅色渐变 + 工具图标 |

## 🚀 快速测试

### 方式一：访问测试页面

访问测试工具页面，一键测试所有错误页面：

```
http://localhost:9501/error-test
```

### 方式二：直接访问测试路由

- 测试 404：http://localhost:9501/error-test/test404
- 测试 401：http://localhost:9501/error-test/test401
- 测试 403：http://localhost:9501/error-test/test403
- 测试 500：http://localhost:9501/error-test/test500
- 测试 503：http://localhost:9501/error-test/test503

### 方式三：测试 API 错误响应

```bash
# 测试 API 404 错误（返回 JSON）
curl -H "Accept: application/json" http://localhost:9501/error-test/api404

# 测试 API 500 错误（返回 JSON）
curl -H "Accept: application/json" http://localhost:9501/error-test/api500
```

## 🎨 设计特点

### 1. 响应式设计
- 适配桌面端和移动端
- 使用媒体查询实现不同屏幕尺寸的优化

### 2. 视觉效果
- 渐变背景，每个错误页面使用不同的配色方案
- Bootstrap Icons 图标库
- CSS 动画效果（bounce, float, shake, rotate 等）
- 毛玻璃效果（backdrop-filter）

### 3. 用户体验
- 清晰的错误说明
- 明确的操作指引（返回首页、返回上页、刷新等）
- 开发环境显示详细错误信息
- 生产环境隐藏技术细节

## 📝 自定义错误页面

### 修改现有页面

直接编辑对应的 `.blade.php` 文件即可：

```bash
# 编辑 404 页面
vim storage/view/errors/404.blade.php
```

### 创建新的错误页面

1. 复制现有模板：
```bash
cp storage/view/errors/404.blade.php storage/view/errors/429.blade.php
```

2. 修改内容（标题、说明、配色等）

3. 在异常处理器中添加对应逻辑（如需要）

### 修改配色方案

每个错误页面在 `<style>` 标签中定义了 CSS 变量：

```css
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --error-color: #667eea;
}
```

修改这些变量即可改变页面配色。

## 🔧 技术实现

### 异常处理流程

```
HTTP 请求
    ↓
发生异常
    ↓
AppExceptionHandler 捕获
    ↓
判断请求类型
    ├─→ API 请求: 返回 JSON
    └─→ 页面请求: 渲染 Blade 模板
```

### 核心文件

- **异常处理器**: `app/Exception/Handler/AppExceptionHandler.php`
- **错误页面**: `storage/view/errors/*.blade.php`
- **测试控制器**: `app/Controller/ErrorTestController.php`

### API 请求判断规则

满足以下任一条件视为 API 请求：
1. 路径以 `/api/` 开头
2. `Accept` 头包含 `application/json`
3. `Content-Type` 头为 `application/json`

## 📚 相关文档

- [错误页面使用指南（完整版）](../../docs/error-pages.md)
- [Hyperf 异常处理文档](https://hyperf.wiki/3.1/#/zh-cn/exception-handler)

## ⚠️ 注意事项

1. **生产环境安全**
   - 500 错误页面在生产环境不显示详细错误信息
   - 通过 `config('app.env')` 和 `config('app.debug')` 判断环境

2. **性能优化**
   - 错误页面使用 CDN 资源（Bootstrap、Bootstrap Icons）
   - 考虑使用本地资源以提高可用性

3. **测试控制器**
   - `ErrorTestController` 仅用于开发测试
   - **生产环境务必删除或禁用**

4. **视图缓存**
   - 修改错误页面后，如未生效，清理视图缓存：
   ```bash
   rm -rf runtime/view/*
   ```

## 🎯 最佳实践

### 1. 错误消息规范

```php
// ✅ 好的做法
throw new NotFoundHttpException('用户不存在');
throw new BusinessException(ErrorCode::FORBIDDEN, '无权访问此资源');

// ❌ 不好的做法
throw new \Exception('error');
throw new \Exception('数据库查询失败');
```

### 2. API 路由规范

```php
// ✅ 推荐：API 路由以 /api/ 开头
Router::addGroup('/api', function () {
    Router::get('/users', 'UserController@index');
});

// ❌ 不推荐：混合路由
Router::get('/users/api/list', 'UserController@apiList');
```

### 3. 日志记录

所有错误都会自动记录到日志：
```bash
# 查看错误日志
tail -f storage/logs/hyperf.log

# 过滤 404 错误
grep "404 Not Found" storage/logs/hyperf.log
```

## 💡 示例代码

### 在控制器中使用

```php
<?php
namespace App\Controller;

use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;

class UserController extends AbstractController
{
    public function show(int $id)
    {
        $user = User::find($id);

        if (!$user) {
            // 抛出 404 错误
            throw new NotFoundHttpException('用户不存在');
        }

        if (!$this->canView($user)) {
            // 抛出 403 错误
            throw new BusinessException(ErrorCode::FORBIDDEN, '无权查看');
        }

        return $this->success($user);
    }
}
```

---

**最后更新**: 2024-10-31
**版本**: 1.0.0

