# SimpleBlog - 简单博客插件

一个极其简单的博客插件，为 Moyi Admin 系统提供基本的博客文章发布和管理功能。

## 功能特性

- ✅ 文章的增删改查
- ✅ 文章分类管理
- ✅ 草稿和发布状态
- ✅ 文章浏览统计
- ✅ 前端博客页面展示
- ✅ 响应式设计
- ✅ 管理后台界面

## 安装步骤

1. **复制插件文件**
   ```bash
   # 将整个 SimpleBlog 目录复制到项目的 addons/ 目录下
   cp -r SimpleBlog /path/to/moyi-admin/addons/
   ```

2. **创建数据库表**
   ```sql
   CREATE TABLE `simple_blog_posts` (
     `id` bigint unsigned NOT NULL AUTO_INCREMENT,
     `title` varchar(255) NOT NULL COMMENT '文章标题',
     `content` text NOT NULL COMMENT '文章内容',
     `status` enum('draft','published') DEFAULT 'draft' COMMENT '状态：draft=草稿，published=已发布',
     `category` varchar(100) DEFAULT NULL COMMENT '文章分类',
     `view_count` int DEFAULT 0 COMMENT '查看次数',
     `created_at` timestamp NULL DEFAULT NULL,
     `updated_at` timestamp NULL DEFAULT NULL,
     PRIMARY KEY (`id`),
     KEY `idx_status` (`status`),
     KEY `idx_category` (`category`),
     KEY `idx_created_at` (`created_at`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='简单博客文章表';
   ```

3. **启用插件**
   - 在管理后台的插件管理页面启用 SimpleBlog 插件
   - 或直接修改 `addons/SimpleBlog/config.php` 中的 `enabled` 为 `true`

4. **清理缓存**
   ```bash
   php bin/hyperf.php di:clear
   php bin/hyperf.php config:clear
   ```

## 使用说明

### 管理后台功能

1. **文章列表页** (`/admin/simple_blog`)
   - 查看所有文章
   - 按状态、分类筛选
   - 搜索文章标题和内容
   - 统计信息展示

2. **创建文章** (`/admin/simple_blog/create`)
   - 输入文章标题和内容
   - 选择发布状态（草稿/发布）
   - 设置文章分类

3. **编辑文章** (`/admin/simple_blog/{id}/edit`)
   - 修改文章内容
   - 更改发布状态
   - 更新分类

### 前端页面功能

1. **博客首页** (`/blog`)
   - 展示已发布的文章列表
   - 搜索功能
   - 分页显示

2. **文章详情页** (`/blog/post/{id}`)
   - 显示完整的文章内容
   - 自动增加浏览量

3. **分类页面** (`/blog/category/{category}`)
   - 按分类展示文章
   - 分类导航

## API 接口

### 管理后台 API

- `GET /api/simple_blog/posts` - 获取文章列表
- `GET /api/simple_blog/posts/{id}` - 获取单篇文章

### 前端页面

所有前端页面都支持公开访问，无需登录即可查看已发布的文章内容。

## 插件配置

在 `config.php` 中可以配置以下选项：

- `display_name`: 插件显示名称
- `posts_per_page`: 每页显示文章数
- `enable_preview`: 启用预览功能
- `allow_public_access`: 允许公开访问

## 文件结构

```
SimpleBlog/
├── info.php                          # 插件基本信息
├── config.php                        # 插件配置
├── routes.php                        # 路由定义
├── README.md                         # 使用说明
├── Controller/
│   ├── Admin/SimpleBlogController.php    # 管理后台控制器
│   └── Web/SimpleBlogWebController.php   # 前端页面控制器
├── Model/
│   └── SimpleBlogPost.php                # 博客文章模型
├── Service/
│   └── SimpleBlogService.php             # 业务逻辑服务
├── Migration/
│   └── CreateSimpleBlogPostsTable.php    # 数据库迁移文件
├── Manager/                           # ⭐ 插件管理配置
│   ├── assets.json                    # 静态资源映射
│   ├── pgsql.json                     # PostgreSQL数据库结构定义 ⭐
│   ├── menus.json                     # 菜单配置
│   ├── permissions.json               # 权限配置
│   ├── menus_permissions.json         # 菜单权限组合配置
│   ├── simple_blog.json               # 插件特定配置
│   ├── Setup.php                      # 插件生命周期管理
│   ├── README.md                      # 管理器说明
│   └── README_PGSQL.md                # PostgreSQL支持说明
└── View/
    ├── admin/simple_blog/                # 管理后台模板
    │   ├── index.blade.php              # 文章列表
    │   ├── create.blade.php             # 创建文章
    │   └── edit.blade.php               # 编辑文章
    └── web/simple_blog/                  # 前端页面模板
        ├── index.blade.php               # 博客首页
        ├── show.blade.php                # 文章详情
        └── category.blade.php            # 分类页面
```

## 注意事项

1. **权限控制**: 插件使用了系统默认的权限中间件，确保只有授权用户才能访问管理功能。

2. **数据验证**: 所有表单提交都包含基础的数据验证，防止恶意输入。

3. **XSS防护**: 模板中使用了适当的转义来防止XSS攻击。

4. **性能优化**: 支持分页加载，避免一次性加载过多数据。

## 扩展开发

插件采用了模块化设计，便于扩展：

- **自定义字段**: 在 `SimpleBlogPost` 模型中添加新字段
- **富文本编辑器**: 集成第三方编辑器组件
- **评论系统**: 添加文章评论功能
- **标签系统**: 为文章添加标签功能
- **SEO优化**: 添加元标签和描述

## 技术支持

如有问题或建议，请联系开发团队。

---

**版本**: 1.0.0
**兼容性**: Moyi Admin >= 1.0.0
**作者**: Moyi Admin Team
