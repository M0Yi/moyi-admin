# 建辉慈善基金会 — 前端 API 接口文档

> 本文档整理自前端项目 `frontend/src/api/` 目录及后端路由定义，供 AI/开发者设计新客户端（如小程序、App）时参考。

---

## 1. 通用约定

### Base URL

| 环境 | URL |
|------|-----|
| 开发（代理） | `/api/v1` |
| 生产 | `http://<host>:6501/api/v1` |

### 统一响应格式

```json
{
  "code": 200,
  "message": "success",
  "data": { ... },
  "meta": {
    "timestamp": 1711500000,
    "request_id": "req_xxxxxx"
  }
}
```

### 分页响应格式（列表接口）

```json
{
  "code": 200,
  "message": "success",
  "data": {
    "items": [ ... ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  }
}
```

### 通用查询参数（GET 列表接口通用）

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码，默认 1 |
| `per_page` / `page_size` | int | 每页条数，默认 12~15 |
| `search` | string | 关键词搜索 |
| `status` | string | 状态筛选 |
| `category_id` | int | 按分类 ID 筛选 |
| `category` | string | 按分类 slug 筛选 |

### 管理端认证

管理端接口需要 Bearer Token：

```
Authorization: Bearer <token>
```

- 登录后 Token 存储于 `localStorage.admin_token`
- 401 响应自动跳转登录页

---

## 2. 公开 API（无需认证）

### 2.1 统计数据 Stats

首页数据展示使用。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/stats/overview` | GET | 获取首页统计概览 |
| `/api/v1/stats/realtime` | GET | 获取实时捐赠动态 |

**GET `/api/v1/stats/overview` Response `data`:**

```json
{
  "historical_total": {
    "amount": 1583775789.69,
    "donor_count": 32580,
    "project_count": 156
  },
  "current_year": {
    "amount": 1234567.89,
    "donor_count": 1523,
    "project_count": 42
  },
  "beneficiaries": {
    "total_count": 8956,
    "current_year_count": 1234
  },
  "online": {
    "today_donations": 45,
    "today_amount": 12345.67
  }
}
```

**GET `/api/v1/stats/realtime` Response `data`:**

```json
{
  "recent_donations": [
    {
      "donor_name": "爱心人士",
      "amount": 100.00,
      "project_name": "致敬困境中的行善者",
      "donated_at": "2024-03-28 14:30:00"
    }
  ]
}
```

---

### 2.2 轮播图 Slides

首页轮播图 / Banner。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/slides` | GET | 获取轮播图列表 |

**Response `data`:**

```json
{
  "items": [
    {
      "id": 1,
      "title": "建辉慈善基金会",
      "subtitle": "让行善者更有力量",
      "description": "致力于发现和致敬...",
      "image": "https://xxx/banner.jpg",
      "image_mobile": "https://xxx/banner-mobile.jpg",
      "link": "",
      "link_url": "",
      "link_text": "了解更多",
      "link_type": "internal",
      "is_active": true,
      "sort_order": 1,
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

---

### 2.3 导航菜单 Navigation

全站导航菜单，支持多级嵌套。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/navigation` | GET | 获取导航菜单树 |

**Response `data`:**

```json
{
  "items": [
    {
      "id": 1,
      "name": "首页",
      "url": "/",
      "icon": "",
      "target": "_self",
      "sort_order": 1,
      "children": []
    },
    {
      "id": 2,
      "name": "项目动态",
      "url": "/articles",
      "icon": "",
      "sort_order": 2,
      "children": [
        {
          "id": 13,
          "name": "行善者生命故事",
          "url": "/articles/life_story_of_good_doer",
          "sort_order": 2
        },
        {
          "id": 14,
          "name": "项目进展",
          "url": "/articles/project_progress",
          "sort_order": 3
        },
        {
          "id": 15,
          "name": "项目效果",
          "url": "/articles/project_effect",
          "sort_order": 4
        },
        {
          "id": 18,
          "name": "活动公告",
          "url": "/articles/activity_notice",
          "sort_order": 5
        }
      ]
    },
    {
      "id": 3,
      "name": "关于我们",
      "url": "/about",
      "sort_order": 3,
      "children": [
        { "id": 4, "name": "我们是谁", "url": "/about/who_we_are" },
        { "id": 5, "name": "基本信息", "url": "/about/basic_info" },
        { "id": 6, "name": "使命与愿景", "url": "/about/mission_vision" },
        { "id": 7, "name": "大事记", "url": "/about/milestones" },
        { "id": 8, "name": "理事会", "url": "/about/council" },
        { "id": 9, "name": "我们的团队", "url": "/about/our_team" },
        { "id": 11, "name": "媒体报道", "url": "/about/media_coverage_about" }
      ]
    },
    {
      "id": 18,
      "name": "信息公开",
      "url": "/disclosure",
      "sort_order": 4,
      "children": [
        { "id": 19, "name": "财务公开", "url": "/disclosure/financial_disclosure" },
        { "id": 20, "name": "机构动态", "url": "/disclosure/mechanism_dynamics" },
        { "id": 21, "name": "机构年报", "url": "/disclosure/institutional_annual_report" },
        { "id": 22, "name": "审计报告", "url": "/disclosure/audit_report" },
        { "id": 23, "name": "款物来源", "url": "/disclosure/donation_history" },
        { "id": 24, "name": "款物去向", "url": "/disclosure/expenditure_history" },
        { "id": 25, "name": "规章制度", "url": "/disclosure/rules_and_regulations" },
        { "id": 26, "name": "致敬捐赠人", "url": "/disclosure/donor" }
      ]
    },
    {
      "id": 27,
      "name": "加入我们",
      "url": "/join-us",
      "sort_order": 5,
      "children": [
        { "id": 28, "name": "我要参与", "url": "/join-us/participate" },
        { "id": 29, "name": "我要工作", "url": "/join-us/work" }
      ]
    },
    {
      "id": 30,
      "name": "合作伙伴",
      "url": "/partners",
      "sort_order": 6
    }
  ]
}
```

---

### 2.4 文章 Articles

新闻动态、项目进展、关于我们等所有文字内容均以文章形式存储。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/articles` | GET | 获取文章列表（分页） |
| `/api/v1/articles/{id}` | GET | 获取文章详情 |
| `/api/v1/categories` | GET | 获取文章分类树 |

**GET `/api/v1/articles` Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码 |
| `per_page` | int | 每页条数 |
| `search` | string | 标题/摘要/内容搜索 |
| `category_id` | int | 按分类 ID 筛选 |
| `category` | string | 按分类 slug 筛选（如 `life_story_of_good_doer`） |
| `status` | string | `published` |

**Response `data.items[]` 单条 Article：**

```json
{
  "id": 1,
  "title": "深圳市建辉慈善基金会简介",
  "slug": "jianhui-foundation-introduction",
  "summary": "建辉慈善基金会是一家致力于...",
  "content": "<h2>机构概况</h2><p>...</p>",
  "cover_image": "https://xxx/cover.jpg",
  "category": {
    "id": 5,
    "name": "我们是谁",
    "slug": "who_we_are"
  },
  "category_id": 5,
  "category_slug": "who_we_are",
  "author": {
    "name": "管理员",
    "avatar": "https://xxx/avatar.jpg"
  },
  "published_at": "2024-01-01T00:00:00Z",
  "published_date": "2024-01-01",
  "view_count": 1520,
  "is_featured": false,
  "is_pinned": false,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**GET `/api/v1/articles/{id}` Response `data`（详情，多了以下字段）：**

```json
{
  "...": "(同列表字段)",
  "attachments": [
    { "name": "文件名.pdf", "url": "https://xxx/file.pdf", "size": 102400 }
  ],
  "related_articles": [
    { "id": 2, "title": "相关文章", "cover_image": "https://xxx/cover.jpg" }
  ],
  "tags": ["公益", "慈善"]
}
```

**GET `/api/v1/categories` Response `data`：**

```json
{
  "items": [
    {
      "id": 1,
      "name": "项目动态",
      "slug": "project_introduction",
      "description": "建辉基金会各类公益项目的最新进展",
      "icon": "",
      "parent_id": 0,
      "article_count": 10,
      "is_single_article": false,
      "linked_article_id": null,
      "children": [
        {
          "id": 2,
          "name": "项目介绍",
          "slug": "project_info",
          "parent_id": 1,
          "article_count": 5,
          "children": []
        }
      ]
    }
  ]
}
```

> **分类与导航的映射关系：** 前端通过导航菜单的 `url` 路径（如 `/articles/life_story_of_good_doer`）提取 slug，调用 `GET /api/v1/articles?category=life_story_of_good_doer` 获取该分类下的文章。对于 `is_single_article: true` 的分类，直接使用 `linked_article_id` 加载单篇文章。

---

### 2.5 项目 Projects

公益项目展示及筹款。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/projects` | GET | 获取项目列表（分页） |
| `/api/v1/projects/{id}` | GET | 获取项目详情 |
| `/api/v1/projects/featured` | GET | 获取精选项目 |

**GET `/api/v1/projects` Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码 |
| `per_page` | int | 每页条数 |
| `search` | string | 标题/描述搜索 |
| `type` | string | 项目类型：`medical` / `health` / `emergency` / `undirected` |
| `status` | string | `active` / `completed` / `paused` |

**GET `/api/v1/projects/featured` Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `limit` | int | 返回数量，默认 6 |

**Response `data.items[]` 单条 Project：**

```json
{
  "id": 1,
  "title": "致敬困境中的行善者",
  "slug": "respect-heroes",
  "subtitle": "为行善者提供关怀",
  "description": "为那些身处困境但依然坚持行善的人们提供关怀和支持",
  "content": "<p>详细内容...</p>",
  "cover_image": "https://xxx/cover.jpg",
  "project_type": "medical",
  "project_type_label": "医疗救助",
  "target_amount": 1000000,
  "goal_amount": 1000000,
  "raised_amount": 500000,
  "donor_count": 1523,
  "progress_percentage": 50,
  "beneficiary_count": 200,
  "status": "active",
  "status_label": "进行中",
  "is_featured": true,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "organization": "建辉慈善基金会",
  "contact_phone": "0755-xxxxxxx",
  "contact_email": "info@jianhui.org",
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-01T00:00:00Z"
}
```

**GET `/api/v1/projects/{id}` Response `data`（详情，多了以下字段）：**

```json
{
  "...": "(同列表字段)",
  "progress": [
    {
      "id": 1,
      "title": "第一批资助发放",
      "description": "已完成第一批50位行善者的资助发放",
      "images": ["https://xxx/img1.jpg", "https://xxx/img2.jpg"],
      "progress_date": "2024-03-01",
      "created_at": "2024-03-01T00:00:00Z"
    }
  ],
  "donations": [
    {
      "id": 1,
      "donor_name": "爱心人士",
      "amount": 100.00,
      "project": { "id": 1, "title": "致敬困境中的行善者" },
      "donation_date": "2024-03-28",
      "donation_date_cn": "2024年3月28日",
      "is_anonymous": false
    }
  ],
  "related_projects": [
    {
      "id": 2,
      "title": "生命故事计划",
      "cover_image": "https://xxx/cover.jpg",
      "progress_percentage": 25
    }
  ]
}
```

---

### 2.6 生命故事 Stories

行善者的人物故事。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/stories` | GET | 获取故事列表（分页） |
| `/api/v1/stories/{id}` | GET | 获取故事详情 |

**GET `/api/v1/stories` Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码 |
| `per_page` | int | 每页条数 |

**Response `data.items[]` 单条 Story：**

```json
{
  "id": 1,
  "title": "致敬困境中的行善者",
  "subtitle": "为行善者提供关怀",
  "summary": "为那些身处困境但依然坚持行善的人们提供关怀和支持",
  "content": "<p>故事详细内容...</p>",
  "story_content": "<p>故事详细内容（富文本）...</p>",
  "cover_image": "https://xxx/cover.jpg",
  "hero_image": "https://xxx/hero.jpg",
  "birth_date": "1956-01-01",
  "death_date": "2020-06-15",
  "age": 64,
  "location": "广东省深圳市",
  "is_featured": true,
  "view_count": 1520,
  "created_at": "2024-01-01T00:00:00Z",
  "updated_at": "2024-01-02T00:00:00Z"
}
```

---

### 2.7 捐赠 Donations

捐赠提交及捐赠披露公开。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/donations` | POST | 提交捐赠 |
| `/api/v1/donations/disclosure` | GET | 获取捐赠披露列表 |

**POST `/api/v1/donations` Request Body：**

```json
{
  "project_id": 1,
  "donor_name": "张三",
  "donor_phone": "13800138000",
  "donor_email": "zhangsan@example.com",
  "amount": 100.00,
  "donation_type": "wechat",
  "is_anonymous": false,
  "message": "希望帮助更多行善者"
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `project_id` | int | 否 | 关联项目 ID |
| `donor_name` | string | 是 | 捐赠人姓名 |
| `donor_phone` | string | 否 | 手机号 |
| `donor_email` | string | 否 | 邮箱 |
| `amount` | number | 是 | 捐赠金额 |
| `donation_type` | string | 是 | `wechat` / `alipay` / `bank` |
| `is_anonymous` | boolean | 否 | 是否匿名，默认 false |
| `message` | string | 否 | 留言 |

**POST `/api/v1/donations` Response `data`：**

```json
{
  "donation_id": 123,
  "order_no": "DON2024032800001",
  "amount": 100.00,
  "payment_url": "",
  "qrcode_url": ""
}
```

**GET `/api/v1/donations/disclosure` Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `page` | int | 页码 |
| `per_page` | int | 每页条数 |
| `project_id` | int | 按项目筛选 |
| `start_date` | string | 起始日期 `YYYY-MM-DD` |
| `end_date` | string | 截止日期 `YYYY-MM-DD` |

**GET `/api/v1/donations/disclosure` Response `data`：**

```json
{
  "items": [
    {
      "id": 1,
      "donor_name": "爱心人士",
      "amount": 100.00,
      "project": { "id": 1, "title": "致敬困境中的行善者" },
      "donation_date": "2024-03-28",
      "donation_date_cn": "2024年3月28日",
      "is_anonymous": false
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 500,
    "last_page": 34
  },
  "stats": {
    "total_donations": 500,
    "total_amount": 1234567.89,
    "total_donors": 1200,
    "projects_count": 10
  }
}
```

---

### 2.8 合作伙伴 Partners

按分类展示合作伙伴。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/partners` | GET | 获取合作伙伴（按分类分组） |

**Response `data`：**

```json
[
  {
    "id": 1,
    "name": "企业合作伙伴",
    "slug": "corporate",
    "description": "与企业共同推动公益",
    "sortOrder": 1,
    "partners": [
      {
        "id": 1,
        "name": "某某科技有限公司",
        "logoUrl": "https://xxx/logo.png",
        "websiteUrl": "https://example.com",
        "description": "长期合作伙伴",
        "sortOrder": 1
      }
    ]
  }
]
```

---

### 2.9 搜索 Search

全局搜索，跨项目、文章、故事。

| 接口 | 方法 | 说明 |
|------|------|------|
| `/api/v1/search` | GET | 全局搜索 |

**Query 参数：**

| 参数 | 类型 | 说明 |
|------|------|------|
| `q` | string | **必填**，搜索关键词 |
| `page` | int | 页码 |
| `per_page` | int | 每页条数 |

**Response `data`：**

```json
{
  "projects": {
    "items": [
      {
        "id": 1,
        "title": "致敬困境中的行善者",
        "cover_image": "https://xxx/cover.jpg",
        "progress_percentage": 50
      }
    ],
    "total": 3
  },
  "articles": {
    "items": [
      {
        "id": 1,
        "title": "基金会简介",
        "cover_image": "https://xxx/cover.jpg"
      }
    ],
    "total": 15
  },
  "stories": {
    "items": [
      {
        "id": 1,
        "title": "张阿姨的十年助学路",
        "cover_image": "https://xxx/cover.jpg"
      }
    ],
    "total": 8
  },
  "meta": {
    "current_page": 1,
    "per_page": 12
  }
}
```

---

## 3. 管理端 API（需认证）

> 所有管理端接口前缀为 `/admin`，需要 `Authorization: Bearer <token>` 头。

### 3.1 认证 Auth

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/login` | POST | 登录（需验证码） |
| `/admin/logout` | GET | 退出登录 |

### 3.2 仪表盘 Dashboard

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/stats` | GET | 综合统计数据 |
| `/admin/category-stats` | GET | 分类统计 |
| `/admin/donation-stats` | GET | 捐赠统计 |
| `/admin/donation-disclosure-stats` | GET | 捐赠披露统计 |
| `/admin/invoice-stats` | GET | 发票统计 |

### 3.3 文章管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/articles` | GET | 文章列表 |
| `/admin/articles/{id}` | GET | 文章详情 |
| `/admin/articles` | POST | 创建文章 |
| `/admin/articles/{id}` | PUT | 更新文章 |
| `/admin/articles/{id}` | DELETE | 删除文章 |

### 3.4 分类管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/categories` | GET | 分类树 |
| `/admin/category-list` | GET | 分类平铺列表 |
| `/admin/categories/{id}` | GET | 分类详情 |
| `/admin/categories` | POST | 创建分类 |
| `/admin/categories/{id}` | PUT | 更新分类 |
| `/admin/categories/{id}` | DELETE | 删除分类 |
| `/admin/categories/order` | POST | 批量更新排序 |

**POST `/admin/categories/order` Body：**

```json
{
  "items": [
    { "id": 1, "sort_order": 1 },
    { "id": 2, "sort_order": 2 }
  ]
}
```

### 3.5 导航管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/navigation` | GET | 导航树 |
| `/admin/navigation-list` | GET | 导航平铺列表 |
| `/admin/navigation/{id}` | GET | 导航项详情 |
| `/admin/navigation` | POST | 创建导航项 |
| `/admin/navigation/{id}` | PUT | 更新导航项 |
| `/admin/navigation/{id}` | DELETE | 删除导航项 |
| `/admin/navigation/order` | POST | 批量更新排序 |

### 3.6 项目管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/projects` | GET | 项目列表 |
| `/admin/projects/{id}` | GET | 项目详情 |
| `/admin/projects` | POST | 创建项目 |
| `/admin/projects/{id}` | PUT | 更新项目 |
| `/admin/projects/{id}` | DELETE | 删除项目 |

### 3.7 轮播图管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/slides` | GET | 轮播图列表 |
| `/admin/slides/{id}` | GET | 轮播图详情 |
| `/admin/slides` | POST | 创建轮播图 |
| `/admin/slides/{id}` | PUT | 更新轮播图 |
| `/admin/slides/{id}` | DELETE | 删除轮播图 |

### 3.8 捐赠管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/donations` | GET | 捐赠列表 |
| `/admin/donations/{id}` | GET | 捐赠详情 |
| `/admin/donations` | POST | 创建捐赠 |
| `/admin/donations/{id}` | PUT | 更新捐赠 |
| `/admin/donations/{id}` | DELETE | 删除捐赠 |
| `/admin/donations/batch-delete` | POST | 批量删除 |
| `/admin/donations-export` | GET | 导出捐赠（返回文件流） |
| `/admin/donation-disclosures` | GET | 披露列表 |
| `/admin/donations/{id}/publish-disclosure` | POST | 发布单条到披露 |
| `/admin/donations/batch-publish-disclosure` | POST | 批量发布到披露 |
| `/admin/donation-disclosures/{id}` | DELETE | 删除披露记录 |
| `/admin/donation-disclosures/batch-delete` | POST | 批量删除披露 |
| `/admin/donations-for-invoice` | GET | 可开票捐赠列表 |

**POST `/admin/donations/batch-delete` Body：**

```json
{ "ids": [1, 2, 3] }
```

### 3.9 发票管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/invoice-applications` | GET | 开票申请列表 |
| `/admin/invoice-applications/{id}` | GET | 申请详情 |
| `/admin/invoice-applications` | POST | 创建申请 |
| `/admin/invoice-applications/{id}/review` | POST | 审核申请 |
| `/admin/invoices` | GET | 发票列表 |
| `/admin/invoices/{applicationId}/create` | POST | 开具发票 |
| `/admin/invoices/{id}/upload-file` | POST | 上传发票文件 |
| `/admin/invoices/{id}/express` | PUT | 更新快递信息 |

### 3.10 合作伙伴管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/partners-list` | GET | 合作伙伴列表 |
| `/admin/partner-categories` | GET | 合作伙伴分类 |
| `/admin/partners/{id}` | GET | 合作伙伴详情 |
| `/admin/partners` | POST | 创建合作伙伴 |
| `/admin/partners/{id}` | PUT | 更新合作伙伴 |
| `/admin/partners/{id}` | DELETE | 删除合作伙伴 |
| `/admin/partners/order` | POST | 批量更新排序 |

### 3.11 文件上传

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/upload` | POST | 上传图片 |
| `/admin/upload/vue` | POST | 上传文件（含子目录） |

**POST `/admin/upload` — `Content-Type: multipart/form-data`：**

| 字段 | 类型 | 说明 |
|------|------|------|
| `file` | File | 文件 |

**POST `/admin/upload/vue` — `Content-Type: multipart/form-data`：**

| 字段 | 类型 | 说明 |
|------|------|------|
| `file` | File | 文件 |
| `sub_path` | string | 子目录，默认 `images` |

### 3.12 系统管理

| 接口 | 方法 | 说明 |
|------|------|------|
| `/admin/system/menus` | CRUD | 菜单管理 |
| `/admin/system/users` | CRUD | 用户管理 |
| `/admin/system/roles` | CRUD | 角色管理（超管） |
| `/admin/system/permissions` | CRUD | 权限管理 |
| `/admin/system/sites` | CRUD | 站点配置 |
| `/admin/system/database-connections` | CRUD | 数据库连接管理 |
| `/admin/system/operation-logs` | GET | 操作日志 |
| `/admin/system/login-logs` | GET | 登录日志 |
| `/admin/system/error-statistics` | GET | 错误统计 |
| `/admin/system/intercept-logs` | GET | 拦截日志 |
| `/admin/system/upload-files` | GET | 文件管理 |
| `/admin/system/crud-generator` | POST | CRUD 生成器（超管） |
| `/admin/system/addons` | CRUD | 插件管理 |
| `/admin/u/{model}` | CRUD | 通用 CRUD 接口 |

---

## 4. 前端页面与 API 调用关系

| 页面 | 路由 | 调用的 API |
|------|------|-----------|
| **首页** | `/` | `stats/overview`, `stats/realtime`, `articles`（精选）, `slides`, `projects/featured` |
| **项目动态列表** | `/articles` | `articles?category=...`, `categories` |
| **文章详情** | `/articles/:id` | `articles/{id}` |
| **分类文章** | `/articles/:slug` | `articles?category=:slug` |
| **项目列表** | `/projects` | `projects`, `categories` |
| **项目详情** | `/projects/:id` | `projects/{id}` |
| **故事列表** | `/stories` | `stories` |
| **故事详情** | `/stories/:id` | `stories/{id}` |
| **捐赠** | `/donate` | `donations`（POST 提交） |
| **捐赠披露** | `/disclosure/donation_history` | `donations/disclosure` |
| **合作伙伴** | `/partners` | `partners` |
| **搜索** | `/search` | `search?q=...` |
| **关于我们** | `/about/:slug` | `articles?category=:slug` |
| **信息公开** | `/disclosure/:slug` | `articles?category=:slug` |
| **加入我们** | `/join-us/:slug` | `articles?category=:slug` |

---

## 5. 数据模型关系图

```
Navigation (导航菜单)
  └── URL slug → Category (文章分类)
                      └── Article[] (文章列表)
                           └── Attachment[] (附件)

Project (公益项目)
  ├── ProjectProgress[] (项目进展)
  ├── DonationRecord[] (捐赠记录)
  └── RelatedProject[] (相关项目)

Story (生命故事)

Slide (轮播图)

PartnerCategory (合作伙伴分类)
  └── Partner[] (合作伙伴)

Donation (捐赠记录)
  └── DonationDisclosure (捐赠披露)
```

---

## 6. 关键设计说明

1. **内容即文章**：关于我们、信息公开、加入我们等页面全部通过「分类 + 文章」体系渲染。分类 slug 对应 URL 路径，文章是内容载体。`is_single_article: true` 的分类直接展示关联文章，无需列表页。

2. **导航驱动路由**：前端路由由导航菜单数据驱动。导航项的 `url` 字段定义了前端路由路径，新客户端可直接使用此数据构建路由和导航栏。

3. **多级分类**：分类支持 `parent_id` 树形结构。一级分类对应导航主菜单项，二级分类对应子菜单项。

4. **Mock 降级**：公开 API 在请求失败时会降级到本地 Mock 数据，新客户端可选择是否实现此机制。

5. **分页统一**：所有列表接口返回 `{ items, meta: { current_page, per_page, total, last_page } }` 结构。

6. **图片字段**：`cover_image`、`image`、`logoUrl` 等图片字段存储完整 URL。
