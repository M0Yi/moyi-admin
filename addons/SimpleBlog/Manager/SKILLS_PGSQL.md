# PostgreSQL 数据库配置文件规范 (pgsql.json)

本文档定义了 `pgsql.json` 文件的结构规范和验证规则，用于确保数据库配置文件的一致性和有效性。

## 目录

- [文件结构概览](#文件结构概览)
- [顶层属性规则](#顶层属性规则)
- [Extensions 配置规则](#extensions-配置规则)
- [Types 配置规则](#types-配置规则)
- [Tables 配置规则](#tables-配置规则)
- [Constraints 配置规则](#constraints-配置规则)
- [Indexes 配置规则](#indexes-配置规则)
- [Triggers 配置规则](#triggers-配置规则)
- [Functions 配置规则](#functions-配置规则)
- [Views 配置规则](#views-配置规则)
- [Sample Data 配置规则](#sample-data-配置规则)
- [命名规范](#命名规范)

---

## 文件结构概览

```json
{
  "version": "1.0.0",
  "description": "配置描述",
  "note": "备注信息",
  "extensions": [],
  "types": {},
  "tables": {},
  "functions": [],
  "views": [],
  "sample_data": []
}
```

### 必填字段

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `version` | string | 是 | 配置版本号，格式：`MAJOR.MINOR.PATCH` |
| `tables` | object | 是 | 表结构定义对象 |
| `functions` | array | 是 | 函数定义数组（可为空） |
| `views` | array | 是 | 视图定义数组（可为空） |

### 可选字段

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `description` | string | `""` | 配置描述 |
| `note` | string | `""` | 备注信息 |
| `extensions` | array | `[]` | 所需 PostgreSQL 扩展列表 |
| `types` | object | `{}` | 自定义类型定义 |
| `sample_data` | array | `[]` | 示例数据 |

---

## 顶层属性规则

### version

- **类型**: string
- **格式**: `MAJOR.MINOR.PATCH` (语义化版本)
- **示例**: `"1.0.0"`, `"2.1.3"`
- **规则**:
  - 必须符合 semver 格式
  - 每次重大更新应递增版本号

### description

- **类型**: string
- **最大长度**: 500 字符
- **规则**:
  - 应简洁描述数据库配置的用途
  - 避免使用特殊字符

### note

- **类型**: string
- **规则**:
  - 可包含重要提示、使用说明等

---

## Extensions 配置规则

### 数组元素规则

每个扩展名必须是有效的 PostgreSQL 扩展名称：

| 扩展名 | 用途 |
|--------|------|
| `pg_trgm` | 全文搜索、模糊匹配 |
| `btree_gin` | GIN 索引的 B-tree 操作符类 |
| `btree_gist` | GiST 索引的 B-tree 操作符类 |
| `pgcrypto` | 加密函数 |
| `uuid-ossp` | UUID 生成 |
| `hstore` | 键值对存储 |
| `jsonb` | JSON 二进制存储 |

### 示例

```json
{
  "extensions": [
    "pg_trgm",
    "btree_gin",
    "btree_gist"
  ]
}
```

### 验证规则

- 数组元素必须是字符串
- 扩展名必须小写
- 不允许重复的扩展名

---

## Types 配置规则

### 类型定义结构

```json
{
  "types": {
    "type_name": "TYPE_DEFINITION"
  }
}
```

### 支持的类型

#### ENUM 类型

```json
{
  "types": {
    "post_status": "ENUM('draft', 'published')",
    "user_status": "ENUM('active', 'inactive', 'pending')"
  }
}
```

#### 规则

- ENUM 值必须用单引号包裹
- 值之间用逗号分隔
- 不允许重复的枚举值
- 枚举值命名规范：
  - 使用小写字母
  - 使用下划线分隔单词

### 自定义类型命名规范

- 使用小写字母和下划线
- 使用单数形式
- 示例: `post_status`, `user_role`, `order_status`

---

## Tables 配置规则

### 表结构定义

```json
{
  "tables": {
    "table_name": {
      "comment": "表注释",
      "columns": {},
      "constraints": [],
      "indexes": [],
      "triggers": []
    }
  }
}
}

### 表命名规范

- **规则**:
  - 使用小写字母和下划线
  - 使用复数形式
  - 必须以字母开头
  - 长度：1-63 字符
  - 不允许使用保留字

- **推荐命名**:
  - 用户表: `users`, `admin_users`
  - 角色表: `roles`, `admin_roles`
  - 关联表: `role_user` (字母序排列)
  - 插件表: `{plugin_name}_{table_name}` 如 `simple_blog_posts`

- **禁止命名**:
  - `order` (PostgreSQL 保留字)
  - `user` (建议用 `users`)
  - `group` (建议用 `groups`)

### comment 字段

- **类型**: string
- **必填**: 是
- **规则**:
  - 必须描述表的用途
  - 中文注释使用中文标点

### Columns 配置规则

#### 列定义结构

```json
{
  "columns": {
    "column_name": {
      "type": "BIGSERIAL",
      "primary": false,
      "nullable": true,
      "default": null,
      "comment": "列注释"
    }
  }
}
```

#### 列命名规范

| 类型 | 命名规则 | 示例 |
|------|----------|------|
| 主键 | `id` | `id BIGSERIAL PRIMARY KEY` |
| 外键 | `{table}_id` | `user_id`, `role_id` |
| 布尔值 | `is_{adjective}` | `is_active`, `is_admin` |
| 时间戳 | `{action}_at` | `created_at`, `updated_at` |
| 软删除 | `deleted_at` | `TIMESTAMPTZ` |
| 状态 | `status` | `TINYINT` |
| 排序 | `sort` | `INT DEFAULT 0` |

#### 支持的列类型

| 类型 | MySQL 等价 | PostgreSQL 类型 |
|------|------------|-----------------|
| 主键自增 | `BIGINT AUTO_INCREMENT` | `BIGSERIAL` |
| 主键自增 | `INT AUTO_INCREMENT` | `SERIAL` |
| 字符串 | `VARCHAR(255)` | `VARCHAR(255)` |
| 长文本 | `TEXT` | `TEXT` |
| 整数 | `INT` | `INTEGER` |
| 大整数 | `BIGINT` | `BIGINT` |
| 小整数 | `TINYINT` | `SMALLINT` |
| 布尔值 | `TINYINT(1)` | `BOOLEAN` |
| 时间戳 | `TIMESTAMP` | `TIMESTAMPTZ` |
| 日期时间 | `DATETIME` | `TIMESTAMPTZ` |
| JSON | `JSON` | `JSONB` |
| 数组 | 不支持 | `TEXT[]`, `INTEGER[]` |
| 全文搜索向量 | 不支持 | `TSVECTOR` |

#### 列属性规则

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `type` | string | - | **必填**，PostgreSQL 数据类型 |
| `primary` | boolean | `false` | 是否为主键 |
| `nullable` | boolean | `true` | 是否允许 NULL |
| `default` | string/null | `null` | 默认值 |
| `comment` | string | `""` | 列注释 |

##### 默认值规则

```json
// 字符串默认值
"default": "'draft'"

// 数字默认值
"default": 0

// 布尔默认值
"default": false

// 时间函数
"default": "NOW()"

// 表达式
"default": "CURRENT_TIMESTAMP"
```

---

## Constraints 配置规则

### 约束定义结构

```json
{
  "constraints": [
    {
      "name": "chk_name",
      "type": "CHECK",
      "condition": "condition_expression"
    },
    {
      "name": "fk_name",
      "type": "FOREIGN KEY",
      "columns": ["column_name"],
      "references": {
        "table": "other_table",
        "columns": ["id"]
      }
    },
    {
      "name": "uq_name",
      "type": "UNIQUE",
      "columns": ["column_name"]
    }
  ]
}
```

### 约束类型

| 类型 | 说明 | 适用场景 |
|------|------|----------|
| `CHECK` | 检查约束 | 验证数据范围、格式 |
| `FOREIGN KEY` | 外键约束 | 表间关联 |
| `UNIQUE` | 唯一约束 | 保证列值唯一 |
| `PRIMARY KEY` | 主键约束 | 定义主键 |

### CHECK 约束规则

#### 常用 CHECK 约束模式

```json
{
  "name": "chk_title_not_empty",
  "type": "CHECK",
  "condition": "length(trim(title)) > 0"
}
```

```json
{
  "name": "chk_age_positive",
  "type": "CHECK",
  "condition": "age >= 0"
}
```

```json
{
  "name": "chk_status_valid",
  "type": "CHECK",
  "condition": "status IN ('active', 'inactive')"
}
```

```json
{
  "name": "chk_published_at_logic",
  "type": "CHECK",
  "condition": "(status = 'published' AND published_at IS NOT NULL) OR (status = 'draft')"
}
```

### 约束命名规范

- CHECK 约束: `chk_{table}_{column}` 或 `chk_{table}_{description}`
- UNIQUE 约束: `uq_{table}_{column}` 或 `uk_{table}_{columns}` (unique key)
- FOREIGN KEY 约束: `fk_{table}_{column}` 或 `fk_{table}_{ref_table}`
- 主键约束: `pk_{table}` 或 `primary`

### 约束命名示例

```json
// 唯一约束 (Unique Key)
{
  "name": "uk_users_username",
  "type": "UNIQUE",
  "columns": ["username"]
}

// 联合唯一约束
{
  "name": "uk_permission_role",
  "type": "UNIQUE",
  "columns": ["permission_id", "role_id"]
}

// 外键约束
{
  "name": "fk_role_user_role",
  "type": "FOREIGN KEY",
  "columns": ["role_id"],
  "references": {
    "table": "admin_roles",
    "columns": ["id"]
  }
}
```

---

## Indexes 配置规则

### 索引定义结构

```json
{
  "indexes": [
    {
      "name": "idx_table_column",
      "columns": ["column_name"],
      "type": "btree",
      "method": "btree",
      "where": "condition",
      "include": ["included_column"]
    }
  ]
}
```

### 索引类型

| 类型 | 说明 | 适用场景 |
|------|------|----------|
| `btree` | B-tree 索引 | 默认类型，适用于大多数场景 |
| `gin` | GIN 索引 | 数组、JSONB、全文搜索 |
| `gist` | GiST 索引 | 几何数据、范围类型 |
| `hash` | Hash 索引 | 等值查询（不支持范围查询） |
| `spgist` | SP-GiST 索引 | 空间分区索引 |
| `brin` | BRIN 索引 | 大表顺序扫描优化 |

### 索引命名规范

| 索引类型 | 命名模式 | 示例 |
|----------|----------|------|
| 单列索引 | `idx_{table}_{column}` | `idx_users_status` |
| 多列索引 | `idx_{table}_{col1}_{col2}` | `idx_posts_user_status` |
| 全文搜索 | `idx_{table}_{column}_vector` | `idx_posts_content_vector` |
| 部分索引 | `idx_{table}_{column}_{condition}` | `idx_posts_status_published` |
| GIN 索引 | `idx_{table}_{column}_gin` | `idx_posts_tags` |
| 唯一索引 | `uk_{table}_{column}` | `uk_users_email` |

### 索引配置示例

#### 普通索引

```json
{
  "name": "idx_simple_blog_posts_status",
  "columns": ["status"],
  "type": "btree"
}
```

#### 联合索引

```json
{
  "name": "idx_posts_user_status",
  "columns": ["user_id", "status"],
  "type": "btree"
}
```

#### GIN 索引（数组）

```json
{
  "name": "idx_simple_blog_posts_tags",
  "columns": ["tags"],
  "type": "gin"
}
```

#### GIN 索引（全文搜索向量）

```json
{
  "name": "idx_simple_blog_posts_content_vector",
  "columns": ["content_vector"],
  "type": "gin"
}
```

#### 部分索引

```json
{
  "name": "idx_simple_blog_posts_featured",
  "columns": ["is_featured"],
  "type": "btree",
  "where": "is_featured = true"
}
```

#### 排序索引

```json
{
  "name": "idx_simple_blog_posts_popular",
  "columns": ["view_count"],
  "type": "btree",
  "where": "status = 'published'"
}
```

#### 包含列索引（PostgreSQL 11+）

```json
{
  "name": "idx_orders_cover",
  "columns": ["user_id"],
  "type": "btree",
  "include": ["created_at", "total_amount"]
}
```

### GIN 索引操作符类

对于 `pg_trgm` 扩展支持的模糊搜索：

```json
{
  "name": "idx_posts_title_trgm",
  "columns": ["title"],
  "type": "gin",
  "method": "gin_trgm_ops"
}
```

---

## Triggers 配置规则

### 触发器定义结构

```json
{
  "triggers": [
    {
      "name": "trigger_name",
      "timing": "BEFORE",
      "events": ["INSERT", "UPDATE"],
      "function": "function_name"
    }
  ]
}
```

### 触发器时机 (timing)

| 时机 | 说明 |
|------|------|
| `BEFORE` | 在事件发生前执行 |
| `AFTER` | 在事件发生后执行 |
| `INSTEAD OF` | 替代事件执行（仅用于视图） |

### 触发器事件 (events)

| 事件 | 说明 |
|------|------|
| `INSERT` | 插入新记录 |
| `UPDATE` | 更新记录 |
| `DELETE` | 删除记录 |
| `TRUNCATE` | 清空表 |

### 触发器命名规范

- 格式: `trg_{table}_{action}` 或 `update_{table}_{column}`
- 示例: `update_users_updated_at`, `set_posts_published_at`

### 常用触发器示例

#### 更新时间戳

```json
{
  "name": "update_simple_blog_posts_updated_at",
  "timing": "BEFORE",
  "events": ["UPDATE"],
  "function": "update_updated_at_column"
}
```

#### 更新全文搜索向量

```json
{
  "name": "update_simple_blog_posts_content_vector",
  "timing": "BEFORE",
  "events": ["INSERT", "UPDATE"],
  "function": "update_content_vector"
}
```

#### 自动设置发布时间

```json
{
  "name": "set_simple_blog_posts_published_at",
  "timing": "BEFORE",
  "events": ["UPDATE"],
  "function": "set_published_at_on_publish"
}
```

---

## Functions 配置规则

### 函数定义结构

```json
{
  "functions": [
    {
      "name": "function_name",
      "parameters": ["param1 TYPE", "param2 TYPE"],
      "returns": "RETURN_TYPE",
      "language": "plpgsql",
      "code": "BEGIN ... END;",
      "description": "函数说明"
    }
  ]
}
```

### 函数命名规范

- 使用小写字母和下划线
- 动词+名词模式
- 示例: `update_updated_at_column`, `search_posts_zh`, `get_posts_stats`

### 函数参数规则

```json
// 无参数
"parameters": []

// 单参数
"parameters": ["query_text TEXT"]

// 多参数
"parameters": [
  "user_id BIGINT",
  "status VARCHAR(20)"
]
```

### 返回类型规则

| 返回类型 | 说明 |
|----------|------|
| `TRIGGER` | 触发器函数 |
| `TABLE(...)` | 返回表 |
| `VOID` | 无返回值 |
| `BIGINT` | 单值返回 |
| `TEXT` | 字符串返回 |
| `SETOF table_name` | 返回表行集合 |

### 函数配置示例

#### 触发器函数

```json
{
  "name": "update_updated_at_column",
  "returns": "TRIGGER",
  "language": "plpgsql",
  "code": "BEGIN NEW.updated_at = NOW(); RETURN NEW; END;",
  "description": "自动更新updated_at字段"
}
```

#### 带参数的函数

```json
{
  "name": "search_blog_posts_zh",
  "parameters": ["query_text TEXT"],
  "returns": "TABLE(id BIGINT, title VARCHAR, content TEXT, rank REAL)",
  "language": "plpgsql",
  "code": "BEGIN RETURN QUERY SELECT t.id, t.title, t.content, ts_rank(t.content_zh_vector, plainto_tsquery('zhparser', query_text)) as rank FROM simple_blog_posts t WHERE t.content_zh_vector @@ plainto_tsquery('zhparser', query_text) AND t.status = 'published' ORDER BY rank DESC; END;",
  "description": "中文全文搜索"
}
```

#### 统计函数

```json
{
  "name": "get_blog_posts_stats",
  "returns": "TABLE(total_posts BIGINT, published_posts BIGINT, draft_posts BIGINT, total_views BIGINT, total_likes BIGINT)",
  "language": "plpgsql",
  "code": "BEGIN RETURN QUERY SELECT COUNT(*)::BIGINT as total_posts, COUNT(*) FILTER (WHERE status = 'published')::BIGINT as published_posts, COUNT(*) FILTER (WHERE status = 'draft')::BIGINT as draft_posts, COALESCE(SUM(view_count), 0)::BIGINT as total_views, COALESCE(SUM(like_count), 0)::BIGINT as total_likes FROM simple_blog_posts; END;",
  "description": "获取博客统计"
}
```

### 语言支持

| 语言 | 说明 |
|------|------|
| `plpgsql` | PostgreSQL 过程语言（推荐） |
| `sql` | SQL 函数 |
| `plperl` | Perl 函数 |
| `plpython3u` | Python 3 函数 |

---

## Views 配置规则

### 视图定义结构

```json
{
  "views": [
    {
      "name": "view_name",
      "query": "SELECT ...",
      "description": "视图说明"
    }
  ]
}
```

### 视图命名规范

- 使用小写字母和下划线
- 使用 `_view` 后缀
- 示例: `blog_posts_published_view`, `user_stats_view`

### 视图配置示例

#### 简单视图

```json
{
  "name": "blog_posts_published_view",
  "query": "SELECT * FROM simple_blog_posts WHERE status = 'published' AND published_at <= NOW() ORDER BY is_pinned DESC, published_at DESC",
  "description": "已发布文章视图"
}
```

#### 聚合视图

```json
{
  "name": "blog_categories_view",
  "query": "SELECT category, COUNT(*) as post_count, MAX(published_at) as last_post_date FROM simple_blog_posts WHERE status = 'published' AND category IS NOT NULL GROUP BY category ORDER BY post_count DESC",
  "description": "文章分类统计"
}
```

#### 带LIMIT的视图

```json
{
  "name": "blog_posts_popular_view",
  "query": "SELECT * FROM simple_blog_posts WHERE status = 'published' ORDER BY view_count DESC, published_at DESC LIMIT 10",
  "description": "热门文章视图"
}
```

---

## Sample Data 配置规则

### 示例数据定义结构

```json
{
  "sample_data": [
    {
      "table": "table_name",
      "description": "数据说明",
      "data": [
        {
          "column_name": "value"
        }
      ]
    }
  ]
}
```

### 示例数据规则

- `table` 必须是已定义的表名
- `data` 是对象数组，每个对象代表一行数据
- 列名必须与表定义中的列名一致
- 不需要包含所有列，未包含的列使用默认值

### 示例数据配置示例

```json
{
  "sample_data": [
    {
      "table": "simple_blog_posts",
      "description": "示例博客文章数据",
      "data": [
        {
          "title": "欢迎使用简单博客",
          "content": "这是一个简单而强大的博客系统。",
          "status": "published",
          "category": "系统介绍",
          "tags": ["博客", "入门"],
          "view_count": 100,
          "like_count": 10,
          "is_featured": true,
          "published_at": "2026-01-27 10:00:00"
        }
      ]
    }
  ]
}
```

---

## 命名规范

### 通用规则

| 项目 | 规则 | 示例 |
|------|------|------|
| 表名 | 小写字母、下划线、复数 | `users`, `blog_posts` |
| 列名 | 小写字母、下划线 | `user_id`, `created_at` |
| 约束名 | `chk_`/`uq_`/`fk_` 前缀 | `chk_users_age` |
| 索引名 | `idx_`/`uk_`/`idx_..._gin` 前缀 | `idx_users_status` |
| 触发器名 | 描述性名称 | `update_users_updated_at` |
| 函数名 | 小写字母、下划线、动词开头 | `get_user_stats` |
| 视图名 | `_view` 后缀 | `users_active_view` |
| 类型名 | 小写字母、下划线 | `post_status` |

### 保留字禁止使用

PostgreSQL 保留字不能直接用作标识符，如需使用需要用双引号包裹：

```sql
-- 禁止
CREATE TABLE order (...);

-- 允许（不推荐）
CREATE TABLE "order" (...);

-- 推荐
CREATE TABLE orders (...);
```

### PostgreSQL 保留字列表（部分）

```
all, and, any, array, as, asc, asymmetric, both, case, cast, check, collate, column, constraint, create, current_catalog, current_date, current_role, current_time, current_timestamp, current_user, default, deferrable, desc, distinct, do, else, end, except, false, fetch, for, foreign, from, grant, group, having, in, initially, intersect, into, lateral, leading, limit, localtime, localtimestamp, not, null, offset, on, only, or, order, placing, primary, references, returning, select, session_user, some, symmetric, table, then, to, trailing, true, union, unique, user, using, variadic, when, where, window, with
```

---

## 完整示例

```json
{
  "version": "1.0.0",
  "description": "简单博客插件 PostgreSQL 数据库配置",
  "note": "包含表结构、索引、约束、触发器、视图、函数和示例数据",
  "extensions": [
    "pg_trgm",
    "btree_gin",
    "btree_gist"
  ],
  "types": {
    "post_status": "ENUM('draft', 'published')"
  },
  "tables": {
    "simple_blog_posts": {
      "comment": "简单博客文章表",
      "columns": {
        "id": {
          "type": "BIGSERIAL",
          "primary": true,
          "comment": "主键ID"
        },
        "title": {
          "type": "VARCHAR(255)",
          "comment": "文章标题"
        },
        "status": {
          "type": "post_status",
          "default": "'draft'",
          "comment": "状态：draft=草稿，published=已发布"
        }
      },
      "constraints": [
        {
          "name": "chk_title_not_empty",
          "type": "CHECK",
          "condition": "length(trim(title)) > 0"
        }
      ],
      "indexes": [
        {
          "name": "idx_posts_status",
          "columns": ["status"],
          "type": "btree"
        }
      ],
      "triggers": [
        {
          "name": "update_posts_updated_at",
          "timing": "BEFORE",
          "events": ["UPDATE"],
          "function": "update_updated_at_column"
        }
      ]
    }
  },
  "functions": [
    {
      "name": "update_updated_at_column",
      "returns": "TRIGGER",
      "language": "plpgsql",
      "code": "BEGIN NEW.updated_at = NOW(); RETURN NEW; END;",
      "description": "自动更新updated_at字段"
    }
  ],
  "views": [
    {
      "name": "posts_published_view",
      "query": "SELECT * FROM simple_blog_posts WHERE status = 'published'",
      "description": "已发布文章视图"
    }
  ],
  "sample_data": [
    {
      "table": "simple_blog_posts",
      "description": "示例数据",
      "data": [
        {
          "title": "测试文章",
          "status": "published"
        }
      ]
    }
  ]
}
```

---

## 版本历史

| 版本 | 日期 | 变更 |
|------|------|------|
| 1.0.0 | 2026-01-28 | 初始版本 |

