# SimpleBlog 插件 PostgreSQL 支持说明

## 概述

SimpleBlog 插件完全兼容 PostgreSQL 数据库，并针对 PostgreSQL 的特性进行了深度优化。插件充分利用了 PostgreSQL 的先进特性，包括全文搜索、JSON 数据存储、数组类型、自定义类型、触发器、视图等。

## ⭐ 当前实现的 PostgreSQL 特性

### 核心功能
- **自动扩展安装**: pg_trgm, btree_gin, btree_gist
- **自定义类型**: post_status 枚举, category_name 域
- **全文搜索**: 中英文双语搜索，实时向量更新
- **JSONB 存储**: 灵活的元数据管理
- **数组类型**: 标签系统实现
- **高级索引**: GIN、部分索引、复合索引
- **触发器系统**: 自动维护搜索向量和时间戳
- **视图和函数**: 统计查询和搜索功能

### 数据表结构
- 使用 PostgreSQL 原生数据类型
- 完整的约束和索引体系
- 智能的默认值和触发器

## 数据库兼容性

### 支持的 PostgreSQL 版本
- PostgreSQL 9.6+
- 推荐使用 PostgreSQL 12.0+

### 数据类型映射

| MySQL 类型 | PostgreSQL 类型 | 说明 |
|-----------|----------------|------|
| bigint unsigned | bigserial | 自增主键ID |
| varchar(255) | varchar(255) | 字符串字段 |
| text | text | 长文本字段 |
| enum('draft','published') | post_status (自定义枚举) | 状态字段 |
| int | integer | 整数字段 |
| timestamp | timestamptz | 带时区时间戳 |
| json | jsonb | JSON数据存储 |
| varchar[] | text[] | 数组类型 |

### 索引优化

插件针对 PostgreSQL 创建了以下索引：
- 状态字段索引：提高按状态查询性能
- 分类字段索引：提高按分类查询性能
- 创建时间索引：提高时间范围查询性能

## PostgreSQL 特性利用

### 全文搜索支持 ⭐
插件已实现完整的 PostgreSQL 全文搜索功能：

```sql
-- 中文全文搜索（已实现）
SELECT id, title, content,
       ts_rank(content_zh_vector, plainto_tsquery('zhparser', '搜索关键词')) as rank
FROM simple_blog_posts
WHERE content_zh_vector @@ plainto_tsquery('zhparser', '搜索关键词')
  AND status = 'published'
ORDER BY rank DESC;
```

**已实现的搜索功能：**
- 中文分词搜索（使用 zhparser 扩展）
- 英文全文搜索
- 搜索结果按相关度排序
- 实时更新搜索向量（通过触发器）

### JSON 数据支持 ⭐
插件使用 PostgreSQL JSONB 类型存储复杂的元数据：

```sql
-- 存储丰富的文章元数据（已实现）
UPDATE simple_blog_posts SET metadata = '{
  "featured": true,
  "pinned": false,
  "difficulty": "intermediate",
  "read_time": 15,
  "seo": {
    "keywords": ["PostgreSQL", "博客"],
    "description": "学习PostgreSQL的最佳实践"
  }
}'::jsonb WHERE id = 1;

-- 查询精选文章
SELECT * FROM simple_blog_posts WHERE (metadata->>'featured')::boolean = true;

-- 按阅读时间筛选
SELECT * FROM simple_blog_posts WHERE (metadata->>'read_time')::integer <= 20;
```

### 数组类型支持 ⭐
使用 PostgreSQL 数组类型存储标签：

```sql
-- 存储文章标签（已实现）
UPDATE simple_blog_posts SET tags = ARRAY['PostgreSQL', '数据库', '教程'];

-- 查询包含特定标签的文章
SELECT * FROM simple_blog_posts WHERE 'PostgreSQL' = ANY(tags);

-- 查询标签数量
SELECT id, array_length(tags, 1) as tag_count FROM simple_blog_posts;
```

## 配置说明

### 数据库连接配置
在插件的 `pgsql.json` 中，PostgreSQL 相关的完整配置：

```json
{
  "version": "1.0.0",
  "description": "简单博客插件 PostgreSQL 数据库配置",
  "extensions": ["pg_trgm", "btree_gin", "btree_gist"],
  "types": {
    "post_status": "ENUM('draft', 'published')",
    "category_name": "VARCHAR(100)"
  },
  "tables": {
    "simple_blog_posts": {
      "comment": "简单博客文章表",
      "columns": {
        "id": {"type": "BIGSERIAL", "primary": true},
        "uuid": {"type": "VARCHAR(36)", "comment": "文章UUID标识符"},
        "title": {"type": "VARCHAR(255)", "comment": "文章标题"},
        "content": {"type": "TEXT", "comment": "文章内容"},
        "content_vector": {"type": "TSVECTOR", "comment": "全文搜索向量"},
        "status": {"type": "post_status", "default": "'draft'"},
        "tags": {"type": "TEXT[]", "comment": "文章标签数组"},
        "metadata": {"type": "JSONB", "comment": "文章元数据"}
      }
    }
  }
}
```

### 迁移文件兼容性
插件的 Migration 文件 `CreateSimpleBlogPostsTable.php` 已经针对不同数据库类型进行了适配。

## 性能优化建议

### 1. 表分区（针对大量数据）
```sql
-- 按创建时间进行分区
CREATE TABLE simple_blog_posts_y2024m01 PARTITION OF simple_blog_posts
FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
```

### 2. 统计信息更新
```sql
-- 更新表统计信息
ANALYZE simple_blog_posts;

-- 查看查询计划
EXPLAIN ANALYZE SELECT * FROM simple_blog_posts WHERE status = 'published';
```

### 3. 连接池配置
确保 PostgreSQL 连接池配置适合博客应用的负载：

```php
// config/autoload/databases.php
'pgsql' => [
    'driver' => env('DB_DRIVER', 'pgsql'),
    'host' => env('DB_HOST', 'localhost'),
    'database' => env('DB_DATABASE', 'moyi_admin'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
    'pool' => [
        'min_connections' => 1,
        'max_connections' => 10,
        'connect_timeout' => 10.0,
        'wait_timeout' => 3.0,
        'heartbeat' => -1,
        'max_idle_time' => 60.0,
    ],
],
```

## 监控和维护

### 常用维护命令

```bash
# 查看表大小
SELECT schemaname, tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size
FROM pg_tables WHERE tablename = 'simple_blog_posts';

# 查看索引使用情况
SELECT indexname, idx_scan, idx_tup_read, idx_tup_fetch
FROM pg_stat_user_indexes WHERE tablename = 'simple_blog_posts';

# 重新建立索引（如果需要）
REINDEX TABLE simple_blog_posts;
```

### 备份建议

```bash
# 备份博客数据
pg_dump -U username -h hostname database_name -t simple_blog_posts > simple_blog_backup.sql

# 恢复数据
psql -U username -h hostname database_name < simple_blog_backup.sql
```

## 故障排除

### 常见问题

1. **字符集问题**
   - 确保数据库使用 UTF8 字符集
   - 检查客户端连接的字符集设置

2. **索引性能问题**
   - 定期运行 `ANALYZE` 更新统计信息
   - 监控慢查询日志

3. **连接超时**
   - 检查 PostgreSQL 配置中的连接超时设置
   - 调整应用连接池配置

### 日志查看

```sql
-- 查看 PostgreSQL 日志中的相关错误
SELECT * FROM pg_log WHERE message LIKE '%simple_blog_posts%';
```

## 总结

SimpleBlog 插件对 PostgreSQL 有良好的支持，能够充分利用 PostgreSQL 的先进特性。在高负载环境下，建议：

1. 使用适当的索引策略
2. 定期维护表统计信息
3. 监控查询性能
4. 考虑使用分区表处理大量数据
5. 配置合适的连接池参数
