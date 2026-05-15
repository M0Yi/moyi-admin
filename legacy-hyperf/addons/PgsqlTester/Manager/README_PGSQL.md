# PostgreSQL 插件配置指南

## 概述

`AddonsPgsqlService` 是专门为 PostgreSQL 数据库设计的插件服务，支持 PostgreSQL 的所有高级特性。

## 配置文件结构

### pgsql.json 完整配置示例

```json
{
  "version": "1.0.0",
  "description": "PostgreSQL 特性展示数据表配置",
  "extensions": [
    "postgres-zhparser",
    "btree_gin",
    "btree_gist",
    "pg_trgm",
    "uuid-ossp",
    "hstore",
    "postgis"
  ],
  "types": {
    "user_status": "ENUM('active', 'inactive', 'suspended')",
    "priority_level": "ENUM('low', 'medium', 'high', 'urgent')",
    "contact_info": "COMPOSITE(name TEXT, email TEXT, phone TEXT)",
    "address_type": "DOMAIN TEXT CHECK(VALUE IN ('home', 'work', 'other'))"
  },
  "tables": {
    "your_table": {
      "comment": "表注释",
      "columns": {
        "id": {
          "type": "BIGSERIAL",
          "primary": true,
          "comment": "主键ID"
        },
        "uuid_field": {
          "type": "UUID",
          "default": "uuid_generate_v4()",
          "comment": "UUID字段"
        },
        "tags": {
          "type": "TEXT[]",
          "comment": "标签数组"
        },
        "metadata": {
          "type": "HSTORE",
          "comment": "键值对存储"
        },
        "settings": {
          "type": "JSONB",
          "comment": "JSON配置数据"
        },
        "location": {
          "type": "GEOMETRY(POINT, 4326)",
          "comment": "地理位置点"
        }
      },
      "constraints": [
        {
          "name": "chk_positive_value",
          "type": "CHECK",
          "condition": "value > 0"
        }
      ],
      "indexes": [
        {
          "name": "idx_tags",
          "columns": ["tags"],
          "type": "gin"
        },
        {
          "name": "idx_metadata",
          "columns": ["metadata"],
          "type": "gin"
        },
        {
          "name": "idx_location",
          "columns": ["location"],
          "type": "gist"
        }
      ],
      "triggers": [
        {
          "name": "trigger_update_timestamp",
          "timing": "BEFORE UPDATE",
          "events": ["UPDATE"],
          "function": "update_updated_at_column()"
        }
      ]
    }
  },
  "functions": [
    {
      "name": "your_function",
      "parameters": ["param1 TEXT", "param2 INTEGER"],
      "returns": "TABLE(result TEXT, count INTEGER)",
      "language": "plpgsql",
      "code": "BEGIN RETURN QUERY SELECT param1, param2; END;"
    }
  ],
  "views": [
    {
      "name": "your_view",
      "query": "SELECT * FROM your_table WHERE status = 'active'"
    }
  ],
  "partitioned_tables": {
    "partitioned_table": {
      "comment": "分区表示例",
      "partition_by": "RANGE (created_at)",
      "columns": {
        "id": {"type": "BIGSERIAL", "primary": true},
        "data": {"type": "JSONB"},
        "created_at": {"type": "TIMESTAMPTZ", "default": "NOW()"}
      },
      "partitions": [
        {
          "name": "partition_2024_01",
          "from": "2024-01-01",
          "to": "2024-02-01"
        }
      ]
    }
  },
  "sample_data": [
    {
      "table": "your_table",
      "data": [
        {
          "title": "示例数据",
          "status": "active",
          "tags": ["example", "test"],
          "metadata": "\"key\"=>\"value\"",
          "settings": "{\"enabled\": true}"
        }
      ]
    }
}
```

## 支持的 PostgreSQL 特性

### 1. 扩展 (Extensions)
- `postgres-zhparser`: 中文分词
- `btree_gin`, `btree_gist`: 高级索引类型
- `pg_trgm`: 字符串相似度搜索
- `uuid-ossp`: UUID 生成
- `hstore`: 键值对存储
- `postgis`: 地理信息系统

### 2. 数据类型 (Types)
- **基本类型**: TEXT, INTEGER, BIGINT, BOOLEAN, TIMESTAMP, etc.
- **高级类型**: UUID, JSONB, HSTORE, GEOMETRY, INET, MACADDR
- **数组类型**: TEXT[], INTEGER[], etc.
- **自定义类型**: ENUM, COMPOSITE, DOMAIN

### 3. 索引类型 (Indexes)
- **B-tree**: 默认索引类型
- **GIN**: 倒排索引，支持数组、JSONB、HSTORE、全文搜索
- **GiST**: 空间索引，支持几何数据、范围查询
- **部分索引**: WHERE 子句过滤
- **表达式索引**: 对表达式创建索引

### 4. 约束 (Constraints)
- CHECK 约束
- UNIQUE 约束
- PRIMARY KEY 约束
- FOREIGN KEY 约束

### 5. 触发器 (Triggers)
- BEFORE/AFTER 触发器
- INSERT/UPDATE/DELETE 事件
- 条件触发器 (WHEN 子句)

### 6. 函数 (Functions)
- PL/pgSQL 语言
- 返回表函数
- 触发器函数

### 7. 视图 (Views)
- 标准视图
- 物化视图 (通过 SQL 直接创建)

### 8. 分区表 (Partitioned Tables)
- RANGE 分区
- LIST 分区
- HASH 分区

## 使用方法

### 1. 在插件中配置 pgsql.json

在插件的 `Manager/pgsql.json` 文件中定义 PostgreSQL 特有的配置。

### 2. 自动安装

当插件安装时，系统会自动：
1. 检查是否为 PostgreSQL 数据库
2. 安装所需的扩展
3. 创建自定义类型
4. 创建表、索引、约束、触发器
5. 创建函数和视图
6. 创建分区表
7. 插入示例数据

### 3. 执行测试查询

```php
// 在控制器中执行测试查询
$addonService = make(AddonService::class);
$testResults = $addonService->executePgsqlTestQueries('YourAddonName');

// 查看测试结果
foreach ($testResults as $result) {
    if ($result['success']) {
        echo "✅ {$result['name']}: {$result['actual_result']} ({$result['execution_time_ms']}ms)\n";
    } else {
        echo "❌ {$result['name']}: {$result['error']}\n";
    }
}
```

## 注意事项

1. **数据库兼容性**: 只有在 PostgreSQL 数据库中才会执行这些特有功能
2. **权限要求**: 需要足够的数据库权限来创建扩展、类型、函数等
3. **依赖顺序**: 扩展和自定义类型会在表之前创建
4. **错误处理**: 单个组件失败不会影响其他组件的安装
5. **升级支持**: 目前不支持自动表结构升级（可扩展）

## 最佳实践

1. **渐进式配置**: 从简单的表开始，逐步添加高级特性
2. **性能考虑**: 合理使用索引，避免过度索引
4. **备份策略**: 在生产环境中操作前务必备份数据库

## 故障排除

### 扩展安装失败
- 检查 PostgreSQL 版本是否支持该扩展
- 确认扩展包已正确安装
- 检查数据库用户权限

### 类型创建失败
- 确保类型名称不冲突
- 检查类型定义语法
- 确认依赖的扩展已安装

### 函数创建失败
- 检查 PL/pgSQL 语法
- 确认参数和返回值类型正确
- 验证函数体逻辑

## 扩展开发

`AddonsPgsqlService` 支持扩展更多 PostgreSQL 特性：

```php
// 在 AddonsPgsqlService 中添加新方法
public function createMaterializedViews(string $addonName, array $views): void
{
    // 实现物化视图创建逻辑
}

public function createPolicies(string $addonName, array $policies): void
{
    // 实现行级安全策略
}
```

这样的设计让插件系统能够充分利用 PostgreSQL 的强大功能，同时保持配置的简洁性和可维护性。