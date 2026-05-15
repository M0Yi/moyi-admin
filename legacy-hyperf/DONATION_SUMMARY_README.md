# 捐赠汇总管理功能说明

## 功能概述

捐赠汇总管理功能用于管理捐赠总额和人数的汇总数据，**不需要指定具体的款项条目**。这对于需要手动维护捐赠统计数据、或从其他系统导入汇总数据的场景非常有用。

## 主要特点

1. **灵活的汇总方式**：支持每日、每月、每年和自定义汇总类型
2. **项目关联**：可以创建全局汇总或关联到具体项目
3. **发布控制**：支持草稿和发布状态，可以控制数据的可见性
4. **排序管理**：可以自定义排序，控制显示顺序
5. **扩展信息**：支持 JSON 格式的扩展字段，灵活存储额外信息

## 数据表结构

### jianhui_org_donation_summaries

| 字段 | 类型 | 说明 |
|------|------|------|
| id | BIGINT | 主键ID |
| title | VARCHAR(200) | 汇总标题 |
| description | TEXT | 描述说明 |
| total_amount | DECIMAL(12,2) | 捐赠总金额 |
| total_donors | BIGINT | 捐赠总人数 |
| summary_date | DATE | 汇总日期 |
| type | VARCHAR(20) | 汇总类型（daily/monthly/yearly/custom） |
| project_id | BIGINT | 关联项目ID（可选） |
| is_published | TINYINT(1) | 是否发布 |
| published_at | DATETIME | 发布时间 |
| sort | INT | 排序 |
| metadata | JSON | 扩展信息 |
| created_at | DATETIME | 创建时间 |
| updated_at | DATETIME | 更新时间 |

## 安装步骤

### 1. 执行数据库迁移

```bash
php install_donation_summary.php
```

或者手动执行 SQL：

```bash
mysql -u root -p your_database < create_donation_summary_table.sql
```

### 2. 访问管理界面

- **管理后台**：`/admin/{adminPath}/jianhui_org/donation-summaries`
- **创建页面**：`/admin/{adminPath}/jianhui_org/donation-summaries/create`

## API 接口

### 获取汇总列表

```
GET /api/v1/admin/donation-summaries
```

**查询参数：**
- `page`: 页码（默认：1）
- `page_size`: 每页数量（默认：20）
- `search`: 搜索关键词
- `sort_field`: 排序字段
- `sort_order`: 排序方向（asc/desc）

### 获取单个汇总

```
GET /api/v1/admin/donation-summaries/{id}
```

### 创建汇总

```
POST /api/v1/admin/donation-summaries
```

**请求体：**
```json
{
  "title": "2024年第一季度捐赠汇总",
  "description": "截至2024年3月31日的累计捐赠数据",
  "total_amount": 1526800.00,
  "total_donors": 3428,
  "summary_date": "2024-03-31",
  "type": "custom",
  "project_id": null,
  "is_published": true,
  "sort": 0
}
```

### 更新汇总

```
PUT /api/v1/admin/donation-summaries/{id}
```

### 删除汇总

```
DELETE /api/v1/admin/donation-summaries/{id}
```

### 发布/取消发布

```
POST /api/v1/admin/donation-summaries/{id}/publish
POST /api/v1/admin/donation-summaries/{id}/unpublish
```

### 获取统计数据

```
GET /api/v1/admin/donation-summaries-stats?project_id=1
```

**响应：**
```json
{
  "code": 200,
  "message": "success",
  "data": {
    "total_amount": 1526800.00,
    "total_donors": 3428,
    "latest_date": "2024-03-31"
  }
}
```

## 使用场景

### 1. 月度捐赠汇总

每月初创建上个月的捐赠汇总，记录当月的总捐赠金额和人数：

```json
{
  "title": "2024年3月捐赠汇总",
  "description": "2024年3月份的月度捐赠统计",
  "total_amount": 458600.00,
  "total_donors": 1056,
  "summary_date": "2024-03-31",
  "type": "monthly"
}
```

### 2. 项目捐赠汇总

为特定项目创建捐赠汇总：

```json
{
  "title": "爱心助学项目捐赠汇总",
  "description": "爱心助学专项的捐赠统计",
  "total_amount": 680000.00,
  "total_donors": 1520,
  "summary_date": "2024-03-31",
  "type": "custom",
  "project_id": 1
}
```

### 3. 全局累计汇总

创建全局累计捐赠汇总：

```json
{
  "title": "建辉慈善累计捐赠汇总",
  "description": "截至2024年3月31日的累计捐赠数据",
  "total_amount": 1526800.00,
  "total_donors": 3428,
  "summary_date": "2024-03-31",
  "type": "custom",
  "project_id": null
}
```

## 汇总类型说明

- **daily**：每日汇总 - 适用于每日捐赠统计
- **monthly**：每月汇总 - 适用于月度报告
- **yearly**：每年汇总 - 适用于年度报告
- **custom**：自定义汇总 - 适用于特殊时期或项目的汇总

## 与捐赠记录的区别

| 特性 | 捐赠记录（JianhuiDonation） | 捐赠汇总（JianhuiDonationSummary） |
|------|---------------------------|----------------------------------|
| 数据粒度 | 每笔具体捐赠 | 汇总统计数据 |
| 金额来源 | 用户实际捐赠 | 手动输入或从其他系统导入 |
| 是否需要具体条目 | 是 | 否 |
| 适用场景 | 记录每笔捐赠 | 维护汇总统计 |
| 数据维护 | 自动/支付对接 | 手动管理 |

## 注意事项

1. **数据一致性**：如果同时使用捐赠记录和捐赠汇总，需要确保数据的一致性
2. **金额精度**：金额字段使用 DECIMAL(12,2)，最大支持 9999亿9999万9999.99元
3. **发布状态**：只有已发布的汇总才会在前端显示
4. **日期管理**：建议按日期顺序创建汇总，便于数据追溯

## 常见问题

### Q1: 捐赠汇总和捐赠披露有什么区别？

**A:** 捐赠汇总用于管理总金额和总人数，不涉及具体款项条目；捐赠披露是公开显示的捐赠明细，包含具体的捐赠人和金额。

### Q2: 如何从其他系统导入汇总数据？

**A:** 可以通过 API 接口批量创建汇总记录，或直接执行 SQL 插入数据。

### Q3: 能否同时使用捐赠记录和捐赠汇总？

**A:** 可以。捐赠记录用于详细管理每笔捐赠，捐赠汇总用于快速查看和展示总体数据。

### Q4: 如何在前端显示最新的汇总数据？

**A:** 调用 `/api/v1/admin/donation-summaries-stats` 接口获取最新发布的汇总数据。

## 更新日志

- **2024-03-25**: 初始版本，创建捐赠汇总管理功能
