# 插件配置说明

## 配置格式

插件现在支持两种配置格式：

### 1. 分别配置（推荐）

将菜单和权限分别配置在不同的文件中：

- `menus.json` - 菜单配置
- `permissions.json` - 权限配置

### 2. 合并配置（向后兼容）

将菜单和权限配置在一个文件中：

- `menus_permissions.json` - 菜单和权限合并配置

## 配置文件格式

### menus.json

菜单配置文件采用数组格式，每个菜单项包含以下字段：

```json
[
  {
    "name": "菜单唯一标识",
    "title": "菜单显示名称",
    "icon": "菜单图标",
    "path": "路由路径",
    "component": "组件路径",
    "redirect": "重定向路径",
    "type": "menu",
    "target": "_self",
    "badge": "徽章文本",
    "badge_type": "徽章类型",
    "permission": "关联权限标识",
    "visible": 1,
    "status": 1,
    "sort": 100,
    "cache": 1,
    "config": null,
    "remark": "备注说明"
  }
]
```

### permissions.json

权限配置文件采用数组格式，每个权限项包含以下字段：

```json
[
  {
    "name": "权限显示名称",
    "slug": "权限唯一标识",
    "type": "menu|button",
    "path": "权限路径",
    "method": "HTTP方法",
    "description": "权限描述",
    "status": 1,
    "sort": 100
  }
]
```

### menus_permissions.json（旧版格式）

合并配置文件格式：

```json
{
  "menus": [
    // 菜单配置数组
  ],
  "permissions": [
    // 权限配置数组
  ]
}
```

## 配置优先级

系统按以下优先级读取配置：

1. 分别配置（`menus.json` + `permissions.json`）
2. 合并配置（`menus_permissions.json`）

如果同时存在多个配置文件，分别配置优先级更高。

## 字段说明

### 菜单字段

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| name | string | 是 | 菜单唯一标识 |
| title | string | 是 | 菜单显示名称 |
| icon | string | 否 | 菜单图标 |
| path | string | 是 | 路由路径 |
| component | string | 否 | 组件路径 |
| redirect | string | 否 | 重定向路径 |
| type | string | 否 | 菜单类型，默认 "menu" |
| target | string | 否 | 打开方式，默认 "_self" |
| badge | string | 否 | 徽章文本 |
| badge_type | string | 否 | 徽章类型 |
| permission | string | 否 | 关联权限标识 |
| visible | int | 否 | 是否可见，默认 1 |
| status | int | 否 | 状态，默认 1 |
| sort | int | 否 | 排序，默认 0 |
| cache | int | 否 | 是否缓存，默认 1 |
| config | mixed | 否 | 额外配置 |
| remark | string | 否 | 备注说明 |

### 权限字段

| 字段 | 类型 | 必需 | 说明 |
|------|------|------|------|
| name | string | 是 | 权限显示名称 |
| slug | string | 是 | 权限唯一标识 |
| type | string | 否 | 权限类型，"menu" 或 "button" |
| path | string | 否 | 权限路径 |
| method | string | 否 | HTTP方法 |
| description | string | 否 | 权限描述 |
| status | int | 否 | 状态，默认 1 |
| sort | int | 否 | 排序，默认 0 |

## 使用建议

1. **新项目**：推荐使用分别配置格式，便于管理和维护
2. **迁移项目**：可以逐步将 `menus_permissions.json` 拆分为两个独立文件
3. **向后兼容**：系统会自动检测和使用合适的配置文件

## 示例

本插件提供了完整的使用示例：

- `menus.json` - 菜单配置示例
- `permissions.json` - 权限配置示例

您可以参考这些文件来配置自己的插件菜单和权限。
