# PostgreSQL 特性测试API接口

## 新增功能概述

本次更新为PgsqlTester插件添加了4个专门的PostgreSQL特性测试API接口，用于测试中文搜索和坐标数据功能。

## 新增API接口

### 1. 中文全文搜索测试
**接口**: `GET /api/pgsql_tester/chinese-search`

**参数**:
- `query` (string): 搜索关键词，必填
- `limit` (int): 返回结果数量限制，默认10，范围1-100

**示例**:
```bash
curl "http://localhost:6501/api/pgsql_tester/chinese-search?query=全文搜索&limit=5"
```

**功能**:
- 使用PostgreSQL的中文分词器(zhparser)进行全文搜索
- 返回匹配度排名和相关信息
- 支持中文关键词搜索

### 2. 地理位置搜索测试
**接口**: `GET /api/pgsql_tester/location-search`

**参数**:
- `lat` (float): 纬度坐标，必填，范围-90到90
- `lng` (float): 经度坐标，必填，范围-180到180
- `radius` (int): 搜索半径(米)，默认1000，范围1-50000

**示例**:
```bash
curl "http://localhost:6501/api/pgsql_tester/location-search?lat=39.9042&lng=116.4074&radius=5000"
```

**功能**:
- 使用PostGIS地理位置计算
- 计算两点间距离
- 支持地理位置范围搜索

### 3. JSONB查询测试
**接口**: `GET /api/pgsql_tester/jsonb-query`

**参数**:
- `searchable` (string): 搜索条件，可选
- `category` (string): 分类条件，可选

**示例**:
```bash
curl "http://localhost:6501/api/pgsql_tester/jsonb-query?searchable=true&category=tech"
```

**功能**:
- 测试PostgreSQL JSONB字段查询
- 支持嵌套字段查询
- 演示JSONB的高效查询性能

### 4. 数组操作测试
**接口**: `GET /api/pgsql_tester/array-query`

**参数**:
- `tag` (string): 标签关键词，必填
- `operator` (string): 操作类型，默认"contains"
  - `contains`: 数组包含指定元素
  - `overlap`: 数组重叠（有交集）
  - `any`: 数组中任意元素匹配

**示例**:
```bash
curl "http://localhost:6501/api/pgsql_tester/array-query?tag=postgresql&operator=contains"
```

**功能**:
- 测试PostgreSQL数组字段操作
- 支持多种数组查询方式
- 演示GIN索引在数组查询中的性能

## 测试数据更新

### 主键字段
所有测试数据现在都包含`id`主键字段，确保：
- 数据更新的幂等性
- 支持INSERT OR UPDATE操作
- 避免重复数据插入

### 坐标数据
新增了地理位置坐标字段：
- `location_lat`: 纬度
- `location_lng`: 经度

测试数据包含了中国的几个主要城市坐标：
- 北京: 39.9042, 116.4074
- 上海: 31.2304, 121.4737
- 深圳: 22.3193, 114.1694
- 成都: 30.5728, 104.0668
- 广州: 23.1291, 113.2644

## 技术实现

### 1. 中文搜索实现
```sql
SELECT id, title, content,
       ts_rank(content_zh_vector, plainto_tsquery('zhparser', ?)) as rank
FROM pgsql_features_demo
WHERE content_zh_vector @@ plainto_tsquery('zhparser', ?)
ORDER BY rank DESC
LIMIT ?
```

### 2. 地理位置搜索实现
```sql
SELECT id, title, location_lat, location_lng,
       ST_Distance(ST_Point(location_lng, location_lat)::geography,
                   ST_Point(?, ?)::geography) as distance_meters
FROM pgsql_features_demo
WHERE location_lat IS NOT NULL AND location_lng IS NOT NULL
  AND ST_DWithin(ST_Point(location_lng, location_lat)::geography,
                 ST_Point(?, ?)::geography, ?)
ORDER BY distance_meters
```

### 3. JSONB查询实现
```sql
SELECT id, title, settings
FROM pgsql_features_demo
WHERE settings->>'searchable' = ?
```

### 4. 数组查询实现
```sql
SELECT id, title, tags
FROM pgsql_features_demo
WHERE ? = ANY(tags)
```

## 安全特性

所有接口都包含以下安全检查：
- IP地址白名单验证
- 请求频率限制
- 用户权限验证
- 参数验证和过滤
- 操作日志记录

## 性能监控

每个接口都会记录：
- 执行时间
- 结果数量
- 错误信息
- 用户操作日志

## 使用建议

1. **中文搜索**: 用于测试PostgreSQL的中文全文搜索功能
2. **地理位置搜索**: 用于测试PostGIS地理位置查询性能
3. **JSONB查询**: 用于测试JSON数据的查询效率
4. **数组查询**: 用于测试数组字段的各种查询方式

这些接口为PostgreSQL的高级特性提供了完整的测试环境，有助于开发和性能调优。
