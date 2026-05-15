# 项目架构说明

## 当前架构：前后端分离

```
┌─────────────────────────────────────────────────────────────┐
│                     moyi-admin 项目                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────────────┐        ┌──────────────────┐          │
│  │   前端 (Vue 3)   │        │   后端 (PHP)     │          │
│  │   port: 3100     │        │   port: 6501     │          │
│  └──────────────────┘        └──────────────────┘          │
│         ↓                           ↑                        │
│    /frontend/                    /app/                     │
│    /addons/JianhuiOrg/                                      │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## 详细说明

### 前端部分 (port 3100)

**位置**: `/Users/moyi/moyi-admin/frontend/`

**技术栈**:
- Vue 3 + TypeScript
- Vite (开发服务器)
- Element Plus (UI组件库)
- Vue Router (路由)
- Pinia (状态管理)

**负责**:
- ✅ 前台页面：首页、项目、文章、故事、捐赠等
- ✅ 后台管理界面：Dashboard、文章管理、项目管理等
- ✅ 用户界面交互
- ✅ 数据展示和表单

**启动命令**:
```bash
cd /Users/moyi/moyi-admin/frontend
npm run dev  # 运行在 http://localhost:3100
```

### 后端部分 (port 6501)

**位置**: `/Users/moyi/moyi-admin/`

**技术栈**:
- PHP 8
- Hyperf 框架
- PostgreSQL 数据库

**负责**:
- ✅ API接口：`/api/v1/*`
- ✅ 数据库操作
- ✅ 业务逻辑
- ✅ 认证授权

**目录**:
- `app/` - 核心应用代码
- `addons/` - 插件（建辉慈善基金会相关）
- `config/` - 配置文件
- `storage/` - 存储文件

**启动命令**:
```bash
cd /Users/moyi/moyi-admin
php bin/hyperf.php start  # 运行在 http://localhost:6501
```

## Vite 代理配置

前端通过 Vite 代理访问后端 API：

```javascript
// vite.config.ts
proxy: {
  '/api': {
    target: 'http://localhost:6501',  // 转发到后端
    changeOrigin: true,
  },
}
```

**请求流程**:
```
浏览器 → Vite (3100) → Hyperf (6501) → 数据库
         ↓
    返回Vue页面
```

## 开发工作流程

### 开发前端
```bash
# 1. 启动前端开发服务器
cd frontend
npm run dev

# 2. 打开浏览器
http://localhost:3100

# 3. 修改 Vue 文件后自动热更新 (HMR)
```

### 开发后端
```bash
# 1. 后端已经在运行 (6501端口)
# 2. 修改 PHP 文件后需要重启后端
cd /Users/moyi/moyi-admin
# 重启 Hyperf 服务器
```

## 前后端交互

### 前端调用后端API
```typescript
// frontend/src/api/admin.ts
import request from '@/utils/request'

export function getStats() {
  return request({
    url: '/admin/stats',  // 实际请求: /api/v1/admin/stats
    method: 'get'
  })
}
```

**URL拼接**:
```
baseURL (/api/v1) + url (/admin/stats) = /api/v1/admin/stats
```

### 后端提供API
```php
// config/routes.php
Router::get('/api/v1/admin/stats', 'Addons\JianhuiOrg\Controller\Api\Admin\AdminApiController@stats');

// 控制器返回JSON
return $this->success([
    'articles' => 1357,
    'projects' => 3,
    'slides' => 3
]);
```

## 数据流向

```
┌─────────────┐
│   浏览器    │
└──────┬──────┘
       │
       ↓ 访问 http://localhost:3100
┌─────────────────────────────────┐
│   Vite Dev Server (3100)       │
│   - 返回 Vue SPA               │
│   - 提供热更新 (HMR)           │
│   - 代理 /api/* 请求           │
└──────┬─────────────────────────┘
       │
       ├─→ /api/v1/* (代理转发)
       │       ↓
       │  ┌─────────────────────────────────┐
       │  │   Hyperf Backend (6501)        │
       │  │   - 处理API请求                │
       │  │   - 查询PostgreSQL数据库      │
       │  │   - 返回JSON数据               │
       │  └─────────────────────────────────┘
       │       ↓
       └─── 返回JSON到Vue组件
               ↓
         更新页面显示
```

## 是否可以在3100写前后端？

### ❌ 不能：3100只运行前端

**原因**:
1. Vite 是**纯前端**开发服务器
2. 只能运行 JavaScript/TypeScript/Vue 代码
3. **不能运行 PHP 代码**

### ✅ 可以：一个项目包含前后端

**当前架构**:
- **一个项目仓库** (`moyi-admin/`)
  - 前端代码在 `frontend/` 目录
  - 后端代码在 `app/`, `addons/` 等目录
- **两个开发服务器**
  - 3100端口：运行前端代码
  - 6501端口：运行后端代码

### 类比：就像一个房子有两个房间

```
🏠 moyi-admin 项目
  ├─ 🛏️ 前端房间 (3100端口)
  │   └─ Vue, TypeScript, SCSS
  │
  └─ 🍳 后端厨房 (6501端口)
      └─ PHP, Hyperf, PostgreSQL
```

## 开发建议

### 同时修改前后端时
1. **两个终端窗口**
   - 终端1: `cd frontend && npm run dev` (3100)
   - 终端2: 后端已运行在6501

2. **修改前端代码**
   - 编辑 `frontend/src/` 下的文件
   - 浏览器自动刷新 (HMR)

3. **修改后端代码**
   - 编辑 `app/` 或 `addons/` 下的 PHP 文件
   - 需要重启后端服务

### 推荐工具
- **前端**: VS Code + Volar (Vue插件)
- **后端**: PhpStorm 或 VS Code + PHP IntelliSense
- **数据库**: TablePlus 或 DBeaver

## 总结

| 端口 | 运行内容 | 技术栈 | 代码位置 |
|-----|---------|--------|---------|
| **3100** | 前端开发服务器 | Vue 3, Vite | `frontend/` |
| **6501** | 后端API服务器 | PHP, Hyperf | `app/`, `addons/` |

**答案**: 您可以在**一个项目**中写前后端代码，但需要**两个服务器**分别运行它们。
