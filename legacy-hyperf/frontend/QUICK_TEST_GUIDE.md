# 后台管理系统测试指南

## 系统访问地址

### 前端开发服务器 (Vue SPA)
- **地址**: http://localhost:3100
- **登录页面**: http://localhost:3100/admin/login
- **控制面板**: http://localhost:3100/admin/dashboard

### 后端 API 服务器
- **地址**: http://localhost:6501
- **API 基础路径**: http://localhost:6501/api/v1

## 浏览器测试步骤

### 1️⃣ 清除浏览器缓存
在打开登录页面前，请先清除缓存：

**Windows/Linux**: `Ctrl + Shift + Delete`
**Mac**: `Cmd + Shift + Delete`

或者使用硬刷新：
**Windows/Linux**: `Ctrl + Shift + R`
**Mac**: `Cmd + Shift + R`

### 2️⃣ 访问登录页面
打开浏览器访问:
```
http://localhost:3100/admin/login
```

### 3️⃣ 登录系统
使用您的后台管理员账号登录。如果您没有账号，需要先在系统中创建一个。

### 4️⃣ 验证功能
登录成功后，您应该能看到：

**控制面板 (`/admin/dashboard`)**:
- ✅ 统计卡片显示: 1357 篇文章, 3 个项目, 3 个轮播图
- ✅ 数据图表
- ✅ 快捷操作按钮

**文章管理 (`/admin/articles`)**:
- ✅ 文章列表（带分页）
- ✅ 搜索和筛选功能
- ✅ 编辑和删除按钮

**项目管理 (`/admin/projects`)**:
- ✅ 3 个项目卡片
- ✅ 进度显示
- ✅ 筹款金额展示

**轮播图管理 (`/admin/slides`)**:
- ✅ 3 个轮播图
- ✅ 图片预览
- ✅ 启用/禁用状态

## API 端点测试

您可以使用以下 curl 命令测试 API：

### 统计数据
```bash
curl http://localhost:3100/api/v1/admin/stats
```

### 获取文章列表
```bash
curl http://localhost:3100/api/v1/admin/articles
```

### 获取轮播图
```bash
curl http://localhost:3100/api/v1/admin/slides
```

### 登录 API (需要真实凭据)
```bash
curl -X POST http://localhost:3100/api/v1/admin/login \
  -H "Content-Type: application/json" \
  -d '{"username":"your_username","password":"your_password"}'
```

## 常见问题

### Q: 登录后显示"系统错误 服务器加载失败"
**A**: 这通常是缓存问题，请：
1. 完全清除浏览器缓存
2. 使用无痕/隐私模式重新访问
3. 检查浏览器控制台 (F12) 查看具体错误信息

### Q: 所有 API 请求失败，显示 "Network Error"
**A**: 请检查：
1. Vite 开发服务器是否正在运行 (http://localhost:3100)
2. 后端 Hyperf 服务器是否正在运行 (http://localhost:6501)
3. 查看浏览器控制台的网络请求，查看具体错误

### Q: 登录提示"用户名或密码错误"
**A**:
1. 确认使用的是真实的管理员账号，不是测试账号 (admin/123456 已移除)
2. 如果忘记密码，需要在数据库中重置
3. 检查账号是否被禁用

### Q: 页面显示空白或白屏
**A**:
1. 打开浏览器控制台 (F12) 查看是否有 JavaScript 错误
2. 检查 Network 标签确认 API 请求是否成功
3. 尝试刷新页面

## 开发者工具

### 浏览器控制台快捷键
- **Chrome/Edge**: `F12` 或 `Ctrl + Shift + I` (Windows) / `Cmd + Option + I` (Mac)
- **Firefox**: `F12` 或 `Ctrl + Shift + K` (Windows) / `Cmd + Option + K` (Mac)
- **Safari**: `Cmd + Option + I` (需要先在偏好设置中启用开发菜单)

### 查看网络请求
1. 打开开发者工具
2. 切换到 "Network" (网络) 标签
3. 刷新页面
4. 查看所有 API 请求的状态码和响应

### 查看 Console 日志
1. 打开开发者工具
2. 切换到 "Console" (控制台) 标签
3. 查看是否有错误信息 (红色) 或警告 (黄色)

## 技术支持

如果遇到问题，请提供以下信息：
1. 浏览器类型和版本
2. 控制台中的错误信息 (截图)
3. Network 标签中失败的 API 请求 (截图)
4. 具体的操作步骤

---

**注意**: 本系统目前处于开发阶段，某些功能可能还在完善中。
