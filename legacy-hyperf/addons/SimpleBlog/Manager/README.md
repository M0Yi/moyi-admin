# SimpleBlog 插件 Manager 目录说明

## 概述

Manager 目录包含了 SimpleBlog 插件的所有配置文件和管理类，用于插件的安装、配置、权限管理等功能。

## 文件结构

```
Manager/
├── assets.json           # 资源文件配置
├── pgsql.json            # PostgreSQL数据库结构配置 ⭐
├── menus.json            # 菜单配置
├── menus_permissions.json # 菜单和权限综合配置
├── permissions.json      # 权限配置
├── simple_blog.json      # 插件详细配置
├── Setup.php             # 插件生命周期管理类
├── README.md             # 本说明文件
└── README_PGSQL.md       # PostgreSQL支持详细说明
```

## 配置文件说明

### assets.json
定义插件的静态资源文件映射关系，包括：
- 视图文件目录映射
- CSS、JS 等静态文件映射

### pgsql.json ⭐
定义插件所需的 PostgreSQL 数据库结构，包括：
- PostgreSQL 扩展安装
- 自定义类型定义
- 表字段定义（使用 PostgreSQL 数据类型）
- 索引配置（包括 GIN 索引用于全文搜索）
- 约束定义
- 触发器配置
- 函数定义
- 视图创建
- 示例数据

### menus.json
定义插件在后台管理系统中的菜单项，包括：
- 菜单名称、图标、路径
- 权限关联
- 显示顺序等

### permissions.json
定义插件所需的权限项，包括：
- 权限名称和标识符
- 权限类型（菜单权限/按钮权限）
- 权限描述等

### menus_permissions.json
综合的菜单和权限配置，通常用于插件安装时批量创建菜单和权限。

### simple_blog.json
插件的详细配置信息，包括：
- 功能特性配置
- 数据库配置
- 路由配置
- 视图配置
- 资源文件配置

## Setup.php 类说明

Setup 类负责处理插件的生命周期事件：

### install()
插件安装时执行，用于初始化插件数据。

### enable()
插件启用时执行，主要工作：
- 检查数据库表是否存在
- 如不存在则创建数据库表
- 执行其他启用逻辑

### disable()
插件禁用时执行，清理临时数据但保留数据库表。

### uninstall()
插件卸载时执行，可选择是否删除数据库表。

## 使用说明

1. **安装插件**：将整个 SimpleBlog 目录复制到项目的 `addons/` 目录下
2. **启用插件**：在管理后台启用插件，Setup 类会自动创建所需的数据库表和权限
3. **配置菜单**：系统会根据 menus.json 和 permissions.json 创建相应的菜单和权限
4. **访问功能**：启用后即可在后台访问简单博客管理功能

## 注意事项

- 所有 JSON 配置文件必须是有效的 JSON 格式
- 数据库表名和字段定义要与 Model 类保持一致
- 权限标识符要唯一，避免与其他插件冲突
- Setup 类的生命周期方法要有异常处理，确保插件操作的稳定性

