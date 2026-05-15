# 建辉慈善官网 - 前后端分离架构方案

## 📋 架构概览

### 当前架构问题
- ❌ 服务端渲染（Blade Templates）
- ❌ 前后端耦合
- ❌ 无独立API接口
- ❌ 缺乏现代化用户体验

### 目标架构
```
┌─────────────────────────────────────────────────────────────┐
│                    前端 SPA (Vue 3)                          │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  Vue Router  │  Pinia  │  Axios  │  Element Plus     │  │
│  └──────────────────────────────────────────────────────┘  │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  视图层        │  组件层        │  API层              │  │
│  │  - 首页        │  - 导航栏      │  - ProjectsAPI      │  │
│  │  - 项目列表    │  - 轮播图      │  - ArticlesAPI      │  │
│  │  - 项目详情    │  - 项目卡片    │  - DonationsAPI     │  │
│  │  - 捐赠表单    │  - 统计卡片    │  - StatsAPI         │  │
│  │  - 新闻中心    │  - 视频播放器  │  - SearchAPI        │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   RESTful API 层                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  /api/v1/projects       - 项目相关接口                │  │
│  │  /api/v1/articles       - 文章相关接口                │  │
│  │  /api/v1/donations      - 捐赠相关接口                │  │
│  │  /api/v1/stories        - 故事相关接口                │  │
│  │  /api/v1/stats          - 统计数据接口                │  │
│  │  /api/v1/search         - 搜索接口                    │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                  后端服务 (Hyperf)                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  API Controllers                                     │  │
│  │  Services (业务逻辑)                                  │  │
│  │  Models (数据模型)                                    │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                  PostgreSQL 数据库                           │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 技术栈选型

### 前端技术栈

#### 核心框架
- **Vue 3.4+** - 渐进式 JavaScript 框架
  - Composition API
  - `<script setup>` 语法
  - 响应式系统

#### 开发语言
- **TypeScript 5.3+** - 类型安全
  - 严格模式
  - 接口定义
  - 类型推断

#### 构建工具
- **Vite 5.0+** - 快速的开发服务器
  - HMR 热更新
  - 生产优化
  - TypeScript 支持

#### 状态管理
- **Pinia 2.1+** - Vue 官方状态管理
  - 模块化 Store
  - TypeScript 支持
  - DevTools 集成

#### 路由
- **Vue Router 4.2+** - 官方路由
  - 嵌套路由
  - 路由守卫
  - 懒加载

#### UI 组件库
- **Element Plus 2.5+** - Vue 3 组件库
  - 丰富的组件
  - 主题定制
  - 按需引入

#### HTTP 客户端
- **Axios 1.6+** - HTTP 请求
  - 拦截器
  - 请求取消
  - 超时处理

#### CSS 方案
- **SCSS** - CSS 预处理器
- **UnoCSS** - 原子化 CSS（可选）

#### 工具库
- **Day.js** - 日期处理
- **Lodash-es** - 工具函数
- **VueUse** - Vue 组合式函数库

### 后端技术栈（保持不变）

- **Hyperf** - PHP 微服务框架
- **PostgreSQL** - 数据库
- **Redis** - 缓存（可选）

---

## 📡 API 接口设计

### 通用规范

#### 基础 URL
```
开发环境: http://localhost:9501/api/v1
生产环境: https://api.jianhuicishan.org/api/v1
```

#### 统一响应格式
```json
{
  "code": 200,
  "message": "success",
  "data": {},
  "meta": {
    "timestamp": 1711234567,
    "request_id": "uuid"
  }
}
```

#### 状态码
- `200` - 成功
- `400` - 请求参数错误
- `401` - 未授权
- `403` - 禁止访问
- `404` - 资源不存在
- `422` - 验证失败
- `500` - 服务器错误

#### 分页格式
```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

---

### 1. 项目相关接口 (`/api/v1/projects`)

#### 1.1 获取项目列表
```
GET /api/v1/projects

Query Parameters:
  - page: number (default: 1)
  - per_page: number (default: 12, max: 50)
  - type: string (medical|health|emergency|undirected)
  - status: string (active|completed|paused)
  - search: string
  - sort: string (latest|progress|amount)

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "致敬困境中的行善者",
        "slug": "tribute-to-good-samaritans",
        "description": "长期项目...",
        "cover_image": "https://cdn...",
        "project_type": "undirected",
        "project_type_label": "非定向",
        "target_amount": 1000000.00,
        "raised_amount": 685000.00,
        "progress_percentage": 68.5,
        "beneficiary_count": 1250,
        "status": "active",
        "status_label": "进行中",
        "is_featured": true,
        "start_date": "2023-01-01",
        "end_date": null,
        "created_at": "2023-01-01T00:00:00Z",
        "updated_at": "2024-03-15T10:30:00Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 12,
      "total": 25,
      "last_page": 3
    }
  }
}
```

#### 1.2 获取项目详情
```
GET /api/v1/projects/{id}

Response:
{
  "code": 200,
  "data": {
    "id": 1,
    "title": "致敬困境中的行善者",
    "slug": "tribute-to-good-samaritans",
    "subtitle": "帮助身处困境的行善者",
    "description": "完整的项目描述...",
    "content": "<p>富文本内容...</p>",
    "cover_image": "https://cdn...",
    "gallery": ["https://cdn...1.jpg", "https://cdn...2.jpg"],
    "project_type": "undirected",
    "project_type_label": "非定向",
    "target_amount": 1000000.00,
    "raised_amount": 685000.00,
    "donor_count": 3420,
    "progress_percentage": 68.5,
    "beneficiary_count": 1250,
    "status": "active",
    "status_label": "进行中",
    "is_featured": true,
    "start_date": "2023-01-01",
        "end_date": null,
    "organization": "建辉慈善基金会",
    "contact_phone": "400-XXX-XXXX",
    "contact_email": "contact@jianhuicishan.org",
    "progress": [
      {
        "id": 1,
        "title": "第一季度进展",
        "description": "已完成120名行善者的帮扶...",
        "images": ["https://cdn..."],
        "progress_date": "2024-03-15",
        "created_at": "2024-03-15T10:00:00Z"
      }
    ],
    "donations": [
      {
        "id": 1,
        "donor_name": "张三",
        "amount": 1000.00,
        "donation_date": "2024-03-20",
        "is_anonymous": false
      }
    ],
    "related_projects": [
      {
        "id": 2,
        "title": "乡村医疗援助计划",
        "cover_image": "https://cdn...",
        "progress_percentage": 64.0
      }
    ],
    "created_at": "2023-01-01T00:00:00Z",
    "updated_at": "2024-03-15T10:30:00Z"
  }
}
```

#### 1.3 获取精选项目
```
GET /api/v1/projects/featured

Query Parameters:
  - limit: number (default: 6, max: 20)

Response:
{
  "code": 200,
  "data": {
    "items": [...]
  }
}
```

---

### 2. 文章相关接口 (`/api/v1/articles`)

#### 2.1 获取文章列表
```
GET /api/v1/articles

Query Parameters:
  - page: number (default: 1)
  - per_page: number (default: 15)
  - category_id: number
  - category_slug: string
  - search: string
  - is_featured: boolean
  - is_pinned: boolean

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "文章标题",
        "slug": "article-slug",
        "summary": "文章摘要...",
        "cover_image": "https://cdn...",
        "category": {
          "id": 1,
          "name": "发现行善者",
          "slug": "find-good-people"
        },
        "author": {
          "name": "作者名",
          "avatar": "https://cdn..."
        },
        "published_date": "2024-03-20",
        "view_count": 1520,
        "is_featured": true,
        "is_pinned": false,
        "created_at": "2024-03-20T10:00:00Z"
      }
    ],
    "meta": {...}
  }
}
```

#### 2.2 获取文章详情
```
GET /api/v1/articles/{id}

Response:
{
  "code": 200,
  "data": {
    "id": 1,
    "title": "文章标题",
    "slug": "article-slug",
    "summary": "文章摘要...",
    "content": "<p>文章内容...</p>",
    "cover_image": "https://cdn...",
    "gallery": ["https://cdn..."],
    "category": {
      "id": 1,
      "name": "发现行善者",
      "slug": "find-good-people"
    },
    "author": {
      "name": "作者名",
      "avatar": "https://cdn..."
    },
    "tags": ["行善者", "公益"],
    "published_date": "2024-03-20",
    "view_count": 1520,
    "like_count": 85,
    "is_featured": true,
    "attachments": [
      {
        "name": "报告.pdf",
        "url": "https://cdn...",
        "size": 2048576
      }
    ],
    "related_articles": [
      {
        "id": 2,
        "title": "相关文章",
        "cover_image": "https://cdn..."
      }
    ],
    "created_at": "2024-03-20T10:00:00Z",
    "updated_at": "2024-03-20T11:00:00Z"
  }
}
```

#### 2.3 获取分类列表
```
GET /api/v1/categories

Query Parameters:
  - type: string (article|story)

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "name": "发现行善者",
        "slug": "find-good-people",
        "description": "发现和报道...",
        "icon": "https://cdn...",
        "parent_id": 0,
        "children": [
          {
            "id": 2,
            "name": "子分类",
            "slug": "sub-category"
          }
        ],
        "article_count": 156
      }
    ]
  }
}
```

---

### 3. 捐赠相关接口 (`/api/v1/donations`)

#### 3.1 提交捐赠
```
POST /api/v1/donations

Request Body:
{
  "project_id": 1,
  "donor_name": "张三",
  "donor_phone": "13800138000",
  "donor_email": "zhangsan@example.com",
  "amount": 1000.00,
  "donation_type": "wechat",
  "is_anonymous": false,
  "message": "加油！"
}

Response:
{
  "code": 200,
  "message": "捐赠提交成功",
  "data": {
    "donation_id": 123,
    "order_no": "DON20240320123456",
    "amount": 1000.00,
    "payment_url": "weixin://pay?...",
    "qrcode_url": "https://cdn...qrcode.png"
  }
}
```

#### 3.2 获取捐赠披露列表
```
GET /api/v1/donations/disclosure

Query Parameters:
  - page: number (default: 1)
  - per_page: number (default: 20)
  - project_id: number
  - start_date: string (YYYY-MM-DD)
  - end_date: string (YYYY-MM-DD)

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "donor_name": "张三",
        "amount": 1000.00,
        "project": {
          "id": 1,
          "title": "致敬困境中的行善者"
        },
        "donation_date": "2024-03-20",
        "donation_date_cn": "2024年03月20日",
        "is_anonymous": false
      }
    ],
    "meta": {...},
    "stats": {
      "total_donations": 3420,
      "total_amount": 2580000.00,
      "total_donors": 2150,
      "projects_count": 12
    }
  }
}
```

---

### 4. 故事相关接口 (`/api/v1/stories`)

#### 4.1 获取故事列表
```
GET /api/v1/stories

Query Parameters:
  - page: number
  - per_page: number
  - is_featured: boolean

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "故事标题",
        "subtitle": "副标题",
        "summary": "故事摘要...",
        "cover_image": "https://cdn...",
        "hero_image": "https://cdn...",
        "birth_date": "1965-03-15",
        "death_date": "2023-12-01",
        "age": 58,
        "location": "河南省商丘市",
        "story_content": "<p>故事内容...</p>",
        "is_featured": true,
        "view_count": 3200,
        "created_at": "2024-03-01T00:00:00Z"
      }
    ],
    "meta": {...}
  }
}
```

#### 4.2 获取故事详情
```
GET /api/v1/stories/{id}

Response: (similar to list item, but with full content)
```

---

### 5. 统计数据接口 (`/api/v1/stats`)

#### 5.1 获取首页统计数据
```
GET /api/v1/stats/overview

Response:
{
  "code": 200,
  "data": {
    "historical_total": {
      "amount": 2183775789.69,
      "donor_count": 152340,
      "project_count": 256
    },
    "current_year": {
      "amount": 47498886.11,
      "donor_count": 12450,
      "project_count": 32
    },
    "beneficiaries": {
      "total_count": 8520,
      "current_year_count": 1250
    },
    "online": {
      "today_donations": 156,
      "today_amount": 85420.00
    }
  }
}
```

#### 5.2 获取实时捐赠动态
```
GET /api/v1/stats/realtime

Response:
{
  "code": 200,
  "data": {
    "recent_donations": [
      {
        "donor_name": "爱心人士",
        "amount": 100.00,
        "project_name": "致敬困境中的行善者",
        "donated_at": "2分钟前"
      }
    ]
  }
}
```

---

### 6. 搜索接口 (`/api/v1/search`)

#### 6.1 全局搜索
```
GET /api/v1/search

Query Parameters:
  - q: string (搜索关键词)
  - type: string (all|projects|articles|stories)
  - page: number
  - per_page: number

Response:
{
  "code": 200,
  "data": {
    "projects": {
      "items": [...],
      "total": 5
    },
    "articles": {
      "items": [...],
      "total": 23
    },
    "stories": {
      "items": [...],
      "total": 2
    },
    "meta": {...}
  }
}
```

---

### 7. 其他接口

#### 7.1 获取轮播图
```
GET /api/v1/slides

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "轮播图标题",
        "image": "https://cdn...",
        "link_url": "/jianhui/project/1",
        "link_type": "project",
        "description": "描述",
        "is_active": true,
        "sort_order": 1
      }
    ]
  }
}
```

#### 7.2 获取导航菜单
```
GET /api/v1/navigation

Response:
{
  "code": 200,
  "data": {
    "items": [
      {
        "id": 1,
        "name": "首页",
        "url": "/",
        "icon": "home",
        "children": []
      },
      {
        "id": 2,
        "name": "关于我们",
        "url": "/about",
        "children": [
          {
            "id": 3,
            "name": "机构简介",
            "url": "/about/intro"
          }
        ]
      }
    ]
  }
}
```

#### 7.3 邮件订阅
```
POST /api/v1/subscribe

Request Body:
{
  "email": "user@example.com"
}

Response:
{
  "code": 200,
  "message": "订阅成功"
}
```

---

## 🏗️ 前端项目结构

```
frontend/
├── public/
│   ├── favicon.ico
│   └── index.html
├── src/
│   ├── api/                    # API 接口层
│   │   ├── index.ts           # Axios 实例配置
│   │   ├── types.ts           # API 类型定义
│   │   ├── projects.ts        # 项目接口
│   │   ├── articles.ts        # 文章接口
│   │   ├── donations.ts       # 捐赠接口
│   │   ├── stories.ts         # 故事接口
│   │   ├── stats.ts           # 统计接口
│   │   └── search.ts          # 搜索接口
│   │
│   ├── assets/                # 静态资源
│   │   ├── images/
│   │   ├── styles/
│   │   │   ├── variables.scss   # 样式变量
│   │   │   ├── mixins.scss      # 样式混入
│   │   │   └── global.scss      # 全局样式
│   │   └── icons/
│   │
│   ├── components/            # 公共组件
│   │   ├── layout/
│   │   │   ├── AppHeader.vue   # 头部导航
│   │   │   ├── AppFooter.vue   # 底部
│   │   │   └── AppBreadcrumb.vue # 面包屑
│   │   ├── common/
│   │   │   ├── AppImage.vue    # 图片组件（懒加载）
│   │   │   ├── AppPagination.vue # 分页组件
│   │   │   ├── AppLoading.vue  # 加载组件
│   │   │   └── AppEmpty.vue    # 空状态组件
│   │   ├── project/
│   │   │   ├── ProjectCard.vue       # 项目卡片
│   │   │   ├── ProjectProgress.vue   # 进度条
│   │   │   └── ProjectStat.vue       # 统计卡片
│   │   ├── article/
│   │   │   ├── ArticleCard.vue       # 文章卡片
│   │   │   └── ArticleListItem.vue   # 文章列表项
│   │   └── donation/
│   │       ├── DonationForm.vue      # 捐赠表单
│   │       └── DonationRecord.vue    # 捐赠记录
│   │
│   ├── composables/           # 组合式函数
│   │   ├── usePagination.ts  # 分页逻辑
│   │   ├── useLoading.ts     # 加载状态
│   │   ├── useDebounce.ts    # 防抖
│   │   └── useFormat.ts      # 格式化工具
│   │
│   ├── router/                # 路由配置
│   │   ├── index.ts
│   │   └── routes/
│   │       ├── home.ts
│   │       ├── projects.ts
│   │       ├── articles.ts
│   │       └── donate.ts
│   │
│   ├── stores/                # Pinia 状态管理
│   │   ├── app.ts            # 应用全局状态
│   │   ├── user.ts           # 用户状态
│   │   ├── project.ts        # 项目状态
│   │   └── donation.ts       # 捐赠状态
│   │
│   ├── types/                 # TypeScript 类型
│   │   ├── index.ts
│   │   ├── project.ts
│   │   ├── article.ts
│   │   └── api.ts
│   │
│   ├── utils/                 # 工具函数
│   │   ├── format.ts         # 格式化
│   │   ├── validate.ts       # 验证
│   │   ├── storage.ts        # 存储封装
│   │   └── constants.ts      # 常量
│   │
│   ├── views/                 # 页面视图
│   │   ├── Home/
│   │   │   └── index.vue
│   │   ├── Projects/
│   │   │   ├── Index.vue           # 项目列表
│   │   │   └── Detail.vue          # 项目详情
│   │   ├── Articles/
│   │   │   ├── Index.vue           # 文章列表
│   │   │   ├── Detail.vue          # 文章详情
│   │   │   └── Category.vue        # 分类页面
│   │   ├── Stories/
│   │   │   ├── Index.vue
│   │   │   └── Detail.vue
│   │   ├── Donate/
│   │   │   └── Index.vue           # 捐赠页面
│   │   ├── DonationDisclosure/
│   │   │   └── index.vue           # 捐赠披露
│   │   ├── About/
│   │   │   └── index.vue
│   │   └── Search/
│   │       └── index.vue
│   │
│   ├── App.vue
│   └── main.ts
│
├── .env.development          # 开发环境变量
├── .env.production           # 生产环境变量
├── .eslintrc.cjs             # ESLint 配置
├── .prettierrc               # Prettier 配置
├── tsconfig.json             # TypeScript 配置
├── vite.config.ts            # Vite 配置
├── package.json
└── README.md
```

---

## 🔧 实施步骤

### 第一阶段：后端 API 开发（3-5天）

#### Day 1-2: API 基础架构
1. 创建 API 控制器基类
   - 统一响应格式
   - 错误处理中间件
   - 请求验证
   - 跨域配置

2. 创建 RESTful API 控制器
   - `ProjectApiController`
   - `ArticleApiController`
   - `DonationApiController`
   - `StoryApiController`
   - `StatsApiController`

3. 配置 API 路由
   ```php
   // config/routes.php
   Router::addGroup('/api/v1', function () {
       // 项目路由
       Router::get('/projects', 'ProjectApiController@index');
       Router::get('/projects/{id}', 'ProjectApiController@show');
       Router::get('/projects/featured', 'ProjectApiController@featured');

       // 文章路由
       Router::get('/articles', 'ArticleApiController@index');
       Router::get('/articles/{id}', 'ArticleApiController@show');
       Router::get('/categories', 'ArticleApiController@categories');

       // 捐赠路由
       Router::post('/donations', 'DonationApiController@store');
       Router::get('/donations/disclosure', 'DonationApiController@disclosure');

       // 统计路由
       Router::get('/stats/overview', 'StatsApiController@overview');
       Router::get('/stats/realtime', 'StatsApiController@realtime');

       // 其他路由...
   });
   ```

#### Day 3-4: 实现核心接口
1. 项目相关接口
2. 文章相关接口
3. 统计数据接口
4. 搜索接口

#### Day 5: API 测试
1. Postman 测试所有接口
2. 性能测试
3. 文档编写

---

### 第二阶段：前端基础架构（2-3天）

#### Day 1: 项目初始化
```bash
# 1. 创建 Vite + Vue 3 + TypeScript 项目
npm create vite@latest frontend -- --template vue-ts

cd frontend

# 2. 安装依赖
npm install vue-router@4 pinia axios element-plus day.js
npm install -D sass @types/node

# 3. 安装 UnoCSS (可选)
npm install -D unocss
```

#### Day 2: 配置开发环境
1. **Vite 配置** (`vite.config.ts`)
   ```typescript
   import { defineConfig } from 'vite'
   import vue from '@vitejs/plugin-vue'
   import { resolve } from 'path'

   export default defineConfig({
     plugins: [vue()],
     resolve: {
       alias: {
         '@': resolve(__dirname, 'src'),
       },
     },
     server: {
       port: 3000,
       proxy: {
         '/api': {
           target: 'http://localhost:9501',
           changeOrigin: true,
         },
       },
     },
   })
   ```

2. **TypeScript 配置** (`tsconfig.json`)

3. **Axios 配置** (`src/api/index.ts`)
   ```typescript
   import axios from 'axios'
   import type { AxiosInstance, AxiosRequestConfig } from 'axios'
   import { ElMessage } from 'element-plus'

   const instance: AxiosInstance = axios.create({
     baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
     timeout: 10000,
     headers: {
       'Content-Type': 'application/json',
     },
   })

   // 请求拦截器
   instance.interceptors.request.use(
     (config) => {
       // 添加 token 等
       return config
     },
     (error) => Promise.reject(error)
   )

   // 响应拦截器
   instance.interceptors.response.use(
     (response) => {
       const { code, message, data } = response.data
       if (code === 200) {
         return data
       } else {
         ElMessage.error(message || '请求失败')
         return Promise.reject(new Error(message))
       }
     },
     (error) => {
       ElMessage.error(error.message || '网络错误')
       return Promise.reject(error)
     }
   )

   export default instance
   ```

4. **路由配置** (`src/router/index.ts`)

5. **Pinia 配置** (`src/stores/index.ts`)

#### Day 3: 基础组件开发
1. 布局组件（Header, Footer）
2. 公共组件（Loading, Pagination）
3. 样式系统（variables.scss, global.scss）

---

### 第三阶段：页面开发（5-7天）

#### Day 1-2: 首页开发
1. 首页布局
2. 轮播图组件
3. 统计数据卡片
4. 精选项目展示
5. 最新文章列表
6. 视频故事组件

#### Day 3-4: 项目模块
1. 项目列表页
   - 筛选器组件
   - 项目卡片
   - 分页加载
2. 项目详情页
   - 项目信息
   - 筹款进度
   - 进展时间轴
   - 爱心捐赠榜
   - 相关项目

#### Day 5: 文章模块
1. 文章列表页
2. 文章详情页
3. 分类页面

#### Day 6: 捐赠模块
1. 捐赠表单页面
2. 捐赠披露页面

#### Day 7: 其他页面
1. 故事列表和详情
2. 关于我们页面
3. 搜索页面

---

### 第四阶段：优化和测试（2-3天）

#### Day 1: 性能优化
1. 代码分割和懒加载
2. 图片优化（懒加载、WebP）
3. 缓存策略
4. 打包优化

#### Day 2: 功能测试
1. 所有功能测试
2. 兼容性测试
3. 响应式测试

#### Day 3: 部署准备
1. 生产环境配置
2. CI/CD 配置
3. 监控和日志

---

## 📝 核心代码示例

### API 接口示例（`src/api/projects.ts`）

```typescript
import request from './index'
import type { Project, ProjectListParams, ProjectListResponse } from './types'

export const projectsApi = {
  // 获取项目列表
  getList(params: ProjectListParams): Promise<ProjectListResponse> {
    return request.get('/projects', { params })
  },

  // 获取项目详情
  getDetail(id: number): Promise<Project> {
    return request.get(`/projects/${id}`)
  },

  // 获取精选项目
  getFeatured(limit = 6): Promise<{ items: Project[] }> {
    return request.get('/projects/featured', { params: { limit } })
  },
}
```

### 组合式函数示例（`src/composables/usePagination.ts`）

```typescript
import { ref, computed } from 'vue'
import type { Ref } from 'vue'

export function usePagination<T>(fetchFn: (params: any) => Promise<any>) {
  const loading = ref(false)
  const data = ref<T[]>([]) as Ref<T[]>
  const total = ref(0)
  const currentPage = ref(1)
  const pageSize = ref(15)

  const fetchData = async (page?: number) => {
    loading.value = true
    try {
      const res = await fetchFn({
        page: page || currentPage.value,
        per_page: pageSize.value,
      })
      data.value = res.items
      total.value = res.meta.total
      currentPage.value = res.meta.current_page
    } finally {
      loading.value = false
    }
  }

  const totalPages = computed(() => Math.ceil(total.value / pageSize.value))

  return {
    loading,
    data,
    total,
    currentPage,
    pageSize,
    totalPages,
    fetchData,
  }
}
```

### 组件示例（`src/components/project/ProjectCard.vue`）

```vue
<template>
  <el-card class="project-card" :body-style="{ padding: '0px' }">
    <div class="card-image">
      <app-image :src="project.cover_image" :alt="project.title" />
      <el-tag v-if="project.is_featured" class="featured-tag" type="danger">
        精选
      </el-tag>
    </div>
    <div class="card-content">
      <el-tag size="small" class="mb-2">
        {{ project.project_type_label }}
      </el-tag>
      <h3 class="project-title">
        <router-link :to="`/projects/${project.id}`">
          {{ project.title }}
        </router-link>
      </h3>
      <p class="project-description">{{ project.description }}</p>

      <project-progress :percentage="project.progress_percentage" />

      <div class="project-stats">
        <span>已筹 {{ formatAmount(project.raised_amount) }}元</span>
        <span>目标 {{ formatAmount(project.target_amount) }}元</span>
      </div>

      <div class="card-actions">
        <el-button type="primary" size="small" @click="viewDetail">
          查看详情
        </el-button>
        <el-button size="small" @click="donate">
          立即捐赠
        </el-button>
      </div>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { Project } from '@/types/project'

interface Props {
  project: Project
}

const props = defineProps<Props>()
const router = useRouter()

const formatAmount = (amount: number) => {
  return new Intl.NumberFormat('zh-CN').format(amount)
}

const viewDetail = () => {
  router.push(`/projects/${props.project.id}`)
}

const donate = () => {
  router.push(`/donate?project_id=${props.project.id}`)
}
</script>

<style scoped lang="scss">
.project-card {
  transition: transform 0.3s, box-shadow 0.3s;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
}

.card-image {
  position: relative;
  height: 200px;

  .featured-tag {
    position: absolute;
    top: 8px;
    right: 8px;
  }
}

.card-content {
  padding: 16px;
}

.project-title {
  margin: 8px 0;
  font-size: 18px;

  a {
    color: inherit;
    text-decoration: none;

    &:hover {
      color: var(--el-color-primary);
    }
  }
}

.project-description {
  color: #666;
  font-size: 14px;
  margin: 8px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.project-stats {
  display: flex;
  justify-content: space-between;
  margin: 12px 0;
  font-size: 13px;
  color: #999;
}

.card-actions {
  display: flex;
  gap: 8px;
}
</style>
```

---

## 🚀 部署方案

### 开发环境
```bash
# 后端
cd /Users/moyi/moyi-admin
php bin/hyperf.php start

# 前端
cd frontend
npm run dev
```

### 生产环境

#### 前端构建
```bash
cd frontend
npm run build
```

#### Nginx 配置
```nginx
server {
    listen 80;
    server_name www.jianhuicishan.org;

    # 前端静态文件
    location / {
        root /var/www/frontend/dist;
        try_files $uri $uri/ /index.html;
    }

    # API 反向代理
    location /api {
        proxy_pass http://127.0.0.1:9501;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}
```

---

## ✅ 验收标准

### 功能验收
- [ ] 所有 API 接口正常工作
- [ ] 前端页面完整实现参考网站功能
- [ ] 响应式设计在移动端正常
- [ ] 表单验证和错误处理
- [ ] 加载状态和空状态处理

### 性能验收
- [ ] 首页加载时间 < 2s
- [ ] Lighthouse 评分 > 90
- [ ] 图片懒加载工作
- [ ] 代码分割生效

### 代码质量
- [ ] TypeScript 类型覆盖率 100%
- [ ] ESLint 无错误
- [ ] 组件可复用性
- [ ] 代码注释完整

---

## 📚 参考资料

### 参考网站
- https://www.hhax.org/ - 韩红爱心慈善基金会

### 技术文档
- Vue 3: https://cn.vuejs.org/
- Vite: https://cn.vitejs.dev/
- Element Plus: https://element-plus.org/
- Pinia: https://pinia.vuejs.org/
- TypeScript: https://www.typescriptlang.org/

---

**文档版本**: v1.0
**创建日期**: 2026-03-23
**更新日期**: 2026-03-23
