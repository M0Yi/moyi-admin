# 后台控制面板数据流向详解

## 📍 完整数据流向

```
用户打开浏览器
    ↓
访问 http://localhost:3100/admin/dashboard
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第1步: 浏览器加载 Vue 页面                                   │
├─────────────────────────────────────────────────────────────┤
│ 文件: src/views/Admin/Dashboard.vue                         │
│                                                              │
│ <script setup>                                              │
│   import * as adminApi from '@/api/admin'  // 导入API模块    │
│                                                              │
│   // 组件挂载时加载数据                                      │
│   onMounted(() => {                                          │
│     loadStats()  // 调用加载函数                             │
│   })                                                         │
│ </script>                                                   │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第2步: Vue 组件调用 API 函数                                │
├─────────────────────────────────────────────────────────────┤
│ 文件: src/views/Admin/Dashboard.vue (line 266)              │
│                                                              │
│ const loadStats = async () => {                             │
│   // 调用 adminApi.getStats()                              │
│   const statsRes = await adminApi.getStats()  // ← 这里！    │
│   ...                                                        │
│ }                                                           │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第3步: API 模块发送 HTTP 请求                              │
├─────────────────────────────────────────────────────────────┤
│ 文件: src/api/admin.ts (line 8-13)                          │
│                                                              │
│ export function getStats() {                                │
│   return request({                                          │
│     url: '/admin/stats',     // 注意：没有 /api/v1 前缀     │
│     method: 'get'                                           │
│   })                                                        │
│ }                                                           │
│                                                              │
│ ↓                                                          │
│ 文件: src/utils/request.ts (line 6-12)                      │
│                                                              │
│ const service: AxiosInstance = axios.create({               │
│   baseURL: '/api/v1',        // 基础URL                   │
│   timeout: 30000,                                           │
│ })                                                          │
│                                                              │
│ 最终URL: /api/v1 + /admin/stats = /api/v1/admin/stats      │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第4步: Vite 代理转发到后端                                  │
├─────────────────────────────────────────────────────────────┤
│ 文件: vite.config.ts (line 16-21)                           │
│                                                              │
│ server: {                                                   │
│   proxy: {                                                  │
│     '/api': {                                               │
│       target: 'http://localhost:6501',  // ← 后端地址       │
│       changeOrigin: true,                                   │
│     },                                                      │
│   },                                                        │
│ }                                                           │
│                                                              │
│ 浏览器请求: http://localhost:3100/api/v1/admin/stats      │
│          ↓                                                  │
│ Vite代理转发: http://localhost:6501/api/v1/admin/stats    │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第5步: 后端路由匹配                                        │
├─────────────────────────────────────────────────────────────┤
│ 文件: config/routes.php (line 73)                           │
│                                                              │
│ Router::get('/api/v1/admin/stats',                          │
│   'Addons\JianhuiOrg\Controller\Api\Admin\AdminApiController@stats'│
│ );                                                          │
│                                                              │
│ URL匹配成功！调用:                                          │
│   Addons\JianhuiOrg\Controller\Api\Admin\AdminApiController  │
│   ->stats() 方法                                           │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第6步: 后端控制器处理请求                                  │
├─────────────────────────────────────────────────────────────┤
│ 文件: addons/JianhuiOrg/Controller/Api/Admin/AdminApiController.php │
│                                                              │
│ class AdminApiController extends BaseApiController          │
│ {                                                           │
│     public function stats()                                 │
│     {                                                       │
│         // 从数据库查询统计数据                              │
│         $stats = [                                         │
│             'articles' => JianhuiArticle::count(),          │
│             'projects' => JianhuiProject::count(),          │
│             'slides' => JianhuiHeroSlide::count(),          │
│             'stories' => JianhuiLifeStory::count(),         │
│             'navigation' => JianhuiNavigation::count(),     │
│             'donations' => 0,                               │
│         ];                                                 │
│                                                              │
│         return $this->success($stats);  // 返回JSON        │
│     }                                                       │
│ }                                                           │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第7步: 数据库查询                                          │
├─────────────────────────────────────────────────────────────┤
│ Hyperf ORM 执行 SQL 查询:                                   │
│                                                              │
│ SELECT COUNT(*) FROM jianhui_articles;   → 1357             │
│ SELECT COUNT(*) FROM jianhui_projects;   → 3                │
│ SELECT COUNT(*) FROM jianhui_hero_slides; → 3               │
│ SELECT COUNT(*) FROM jianhui_life_stories; → 1              │
│ SELECT COUNT(*) FROM jianhui_navigation;  → 29              │
│                                                              │
│ PostgreSQL 数据库 (端口 5432)                               │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第8步: 后端返回 JSON 响应                                   │
├─────────────────────────────────────────────────────────────┤
│ HTTP 200 OK                                                 │
│ Content-Type: application/json                              │
│                                                              │
│ {                                                           │
│   "code": 200,                                              │
│   "message": "success",                                      │
│   "data": {                                                 │
│     "articles": 1357,                                       │
│     "projects": 3,                                          │
│     "slides": 3,                                            │
│     "stories": 1,                                           │
│     "navigation": 29,                                       │
│     "donations": 0                                          │
│   },                                                        │
│   "meta": {                                                 │
│     "timestamp": 1774362480,                                │
│     "request_id": "req_69c29f70590bb4.41267839"            │
│   }                                                         │
│ }                                                           │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第9步: Vite 代理转发响应回浏览器                            │
├─────────────────────────────────────────────────────────────┤
│ 后端响应 → Vite 代理 → 浏览器                               │
└─────────────────────────────────────────────────────────────┘
    ↓
┌─────────────────────────────────────────────────────────────┐
│ 第10步: 前端接收并显示数据                                 │
├─────────────────────────────────────────────────────────────┤
│ 文件: src/views/Admin/Dashboard.vue (line 268-273)         │
│                                                              │
│ const statsRes = await adminApi.getStats()                 │
│                                                              │
│ if (statsRes?.data) {                                      │
│   const data = statsRes.data                               │
│   stats.value[0].value = data.articles?.toString() || '0'  │
│   stats.value[1].value = data.projects?.toString() || '0'  │
│   stats.value[2].value = data.slides?.toString() || '0'    │
│   stats.value[3].value = data.stories?.toString() || '0'   │
│ }                                                           │
│                                                              │
│ Vue 自动更新页面显示：                                       │
│ - 文章总数: 1357                                            │
│ - 项目总数: 3                                               │
│ - 轮播图: 3                                                 │
│ - 故事: 1                                                   │
└─────────────────────────────────────────────────────────────┘
```

## 🔍 关键代码文件清单

### 前端部分 (port 3100)

| 文件 | 作用 | 关键代码 |
|-----|------|---------|
| `src/views/Admin/Dashboard.vue` | 控制面板页面 | `adminApi.getStats()` |
| `src/api/admin.ts` | API 函数定义 | `export function getStats()` |
| `src/utils/request.ts` | HTTP 请求配置 | `baseURL: '/api/v1'` |
| `vite.config.ts` | Vite 配置 | `proxy: { '/api': ... }` |

### 后端部分 (port 6501)

| 文件 | 作用 | 关键代码 |
|-----|------|---------|
| `config/routes.php` | 路由定义 | `Router::get('/api/v1/admin/stats', ...)` |
| `addons/JianhuiOrg/Controller/Api/Admin/AdminApiController.php` | 控制器 | `public function stats()` |
| `addons/JianhuiOrg/Model/JianhuiArticle.php` | 文章模型 | `JianhuiArticle::count()` |
| PostgreSQL 数据库 | 数据存储 | `SELECT COUNT(*) FROM ...` |

## ⏱️ 请求时间线

```
0ms    用户访问 /admin/dashboard
50ms   Vue 组件挂载，触发 loadStats()
55ms   发起 HTTP GET /api/v1/admin/stats
56ms   Vite 代理转发到 localhost:6501
60ms   后端接收请求，执行数据库查询
65ms   PostgreSQL 返回 COUNT(*) 结果
70ms   后端组装 JSON 响应
75ms   返回 HTTP 200 + JSON 数据
76ms   Vite 代理转发响应回浏览器
80ms   前端接收数据，更新 Vue 状态
85ms   Vue 重新渲染页面，用户看到数据

总计: ~85ms
```

## 🎯 核心要点

### 1. URL 拼接规则

```typescript
// 前端请求
baseURL: '/api/v1'    (src/utils/request.ts)
+ url: '/admin/stats' (src/api/admin.ts)
= '/api/v1/admin/stats'
```

### 2. 代理转发

```
浏览器访问: http://localhost:3100/api/v1/admin/stats
              ↓
         Vite 代理转发
              ↓
后端接收: http://localhost:6501/api/v1/admin/stats
```

### 3. 数据库查询

```php
// Hyperf ORM 自动转换为 SQL
JianhuiArticle::count()
→ SELECT COUNT(*) FROM jianhui_articles

JianhuiProject::count()
→ SELECT COUNT(*) FROM jianhui_projects
```

### 4. 响应格式

```json
{
  "code": 200,           // 状态码
  "message": "success",  // 消息
  "data": {             // 实际数据
    "articles": 1357,
    "projects": 3,
    "slides": 3
  },
  "meta": {             // 元数据
    "timestamp": 1774362480,
    "request_id": "req_xxx"
  }
}
```

## 🔄 完整循环

```
数据库
  ↑ 提供数据
  ↓
后端API (6501) ← 查询数据库
  ↑ 返回JSON
  ↓
前端Vue (3100) ← 调用API
  ↑ 显示数据
  ↓
浏览器 ← 渲染页面
  ↑ 用户交互
  ↓
用户
```

这就是后台控制面板从数据库获取并显示数据的完整流程！🎉
