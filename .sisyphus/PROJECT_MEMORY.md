# MoYi Admin - 项目长期记忆文档

> **最后更新**: 2026-03-07
> **维护者**: AI Assistant
> **用途**: AI 代理的长期项目记忆，包含项目关键信息、开发规范、已知问题和待办事项

---

## 📋 **项目基本信息**

### **项目概况**
- **项目名称**: MoYi Admin
- **项目类型**: Hyperf-based PHP Admin System
- **技术栈**: PHP 8.3+ / Hyperf / Swoole / MySQL / PostgreSQL / Redis
- **当前版本**: v1.0.0
- **开发环境**: macOS Darwin
- **项目路径**: `/Users/moyi/claude/moyi-admin`

### **核心功能**
- ✅ 多站点管理系统
- ✅ RBAC 权限管理
- ✅ 动态菜单系统
- ✅ 通用 CRUD 生成器
- ✅ 插件系统
- ✅ AI Agent 集成
- ✅ PostgreSQL 支持（含 zhparser 中文分词）

---

## 🌐 **访问信息**

### **开发环境**
- **后台入口**: http://127.0.0.1:6501/admin/dev/login
- **超级管理员账号**: 
  - 用户名: `admin`
  - 密码: `123456`
- **注意事项**: 
  - 如果已登录，应直接跳转到后台首页
  - 后台入口路径可配置（`ADMIN_ENTRY_PATH` 环境变量）
  - 当前测试环境入口为 `/admin/dev`

### **服务端口**
- **HTTP Server**: `0.0.0.0:6501`
- **MySQL**: `localhost:3306`
- **PostgreSQL**: `localhost:5432`
- **Redis**: `localhost:6379`

---

## 🗄️ **数据库配置**

### **MySQL**
```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=moyi_admin
DB_USERNAME=moyi
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### **PostgreSQL**
```env
PG_HOST=localhost
PG_PORT=5432
PG_DATABASE=moyi_admin
PG_USERNAME=moyi
PG_PASSWORD=
PG_CHARSET=utf8
```

**已安装扩展**:
- ✅ `pdo_pgsql` 扩展
- ✅ `zhparser` 中文分词扩展（PostgreSQL 17）

**PostgreSQL 连接测试**:
```bash
/usr/local/opt/postgresql@17/bin/psql -h localhost -U moyi -d moyi_admin
```

---

## 🏗️ **项目架构**

### **目录结构**
```
moyi-admin/
├── app/
│   ├── Controller/          # 控制器
│   │   ├── Admin/           # 后台控制器
│   │   │   ├── BaseModelCrudController.php  # CRUD 基类（1322行）
│   │   │   ├── System/      # 系统管理
│   │   │   └── AiAgent/     # AI Agent 相关
│   │   └── ...
│   ├── Service/             # 服务层
│   │   └── Admin/
│   │       └── BaseService.php  # 服务基类
│   ├── Model/               # 数据模型
│   ├── Middleware/          # 中间件
│   ├── Exception/           # 异常处理
│   └── Support/             # 辅助工具
├── config/                  # 配置文件
│   └── routes/              # 路由配置
├── storage/                 # 存储目录
│   ├── view/                # 视图模板
│   └── logs/                # 日志文件
├── addons/                  # 插件目录
│   ├── SimpleBlog/          # 简单博客插件
│   ├── AddonsStore/         # 插件商店
│   └── PgsqlTester/         # PostgreSQL 测试插件
├── runtime/                 # 运行时文件
│   ├── container/           # 依赖注入容器
│   └── logs/                # 运行时日志
└── .sisyphus/              # AI 代理工作目录
    ├── plans/               # 工作计划
    ├── drafts/              # 草稿文件
    └── PROJECT_MEMORY.md    # 本文件
```

### **核心基类**

#### **1. BaseModelCrudController** (1322 行)
- **路径**: `app/Controller/Admin/BaseModelCrudController.php`
- **职责**: 提供 CRUD 操作的基类实现
- **关键方法**:
  - `index()` - 列表页面
  - `listData()` - 列表数据接口
  - `create()` - 创建页面
  - `store()` - 保存数据
  - `edit()` - 编辑页面
  - `update()` - 更新数据
  - `destroy()` - 删除数据
  - `batchDestroy()` - 批量删除
  - `validateData()` - 数据验证
  - `getListQuery()` - 查询构建

#### **2. BaseService**
- **路径**: `app/Service/Admin/BaseService.php`
- **职责**: 服务层的基类
- **关键方法**:
  - `getList()` - 获取列表数据
  - `find()` - 查找单条记录
  - `create()` - 创建记录
  - `update()` - 更新记录
  - `delete()` - 删除记录
  - `batchDelete()` - 批量删除

---

## 🔧 **开发规范**

### **Git 提交规范**
```bash
<type>(<scope>): <subject>

# 类型：
feat     - 新功能
fix      - 修复 bug
refactor - 重构
docs     - 文档
style    - 代码格式
test     - 测试
chore    - 构建/工具
```

**示例**:
```
feat(install): 添加 PostgreSQL 数据库检测功能
fix(auth): 修复登录状态检查问题
refactor(controller): 优化 Form Schema 构建逻辑
```

### **代码风格**
- PHP 8.3+ 严格类型: `declare(strict_types=1);`
- 遵循 PSR-12 编码规范
- 使用类型声明和返回类型
- 使用 `#[Inject]` 注解进行依赖注入

### **命名约定**
- 控制器: `XxxController.php`
- 服务: `XxxService.php`
- 模型: `AdminXxx.php` (Admin 前缀)
- 方法: camelCase
- 常量: UPPER_SNAKE_CASE

---

## 🚀 **常用命令**

### **启动服务**
```bash
# 开发模式（热重载）
php bin/hyperf.php server:watch

# 生产模式
php bin/hyperf.php start

# 后台运行
php bin/hyperf.php start > /tmp/hyperf.log 2>&1 &
```

### **数据库操作**
```bash
# MySQL
mysql -u moyi -D moyi_admin

# PostgreSQL
/usr/local/opt/postgresql@17/bin/psql -h localhost -U moyi -d moyi_admin

# 清空数据库（重新安装）
mysql -u moyi -e "DROP DATABASE moyi_admin; CREATE DATABASE moyi_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
redis-cli FLUSHALL
```

### **代码质量**
```bash
# 语法检查
php -l app/Controller/Admin/InstallController.php

# 查找重复代码
vendor/bin/phpcpd --min-lines 5 --min-tokens 30 app/

# 静态分析
vendor/bin/phpstan analyse app
```

### **Git 操作**
```bash
# 创建功能分支
git checkout -b feature/xxx

# 提交更改
git add .
git commit -m "feat(xxx): description"

# 推送到远程
git push origin feature/xxx

# 创建 PR
gh pr create --repo M0Yi/moyi-admin --head m0yi-bot:feature/xxx
```

---

## 📦 **插件系统**

### **已安装插件**
1. **SimpleBlog** - 简单博客系统
2. **AddonsStore** - 插件商店
3. **HomePageDemo** - 首页演示
4. **PgsqlTester** - PostgreSQL 测试工具

### **插件开发规范**
- 插件目录: `addons/{PluginName}/`
- 必需文件:
  - `Plugin.php` - 插件主类
  - `config.php` - 插件配置
  - `routes.php` - 路由定义（可选）

---

## ⚠️ **已知问题**

### **1. 代码重复问题** 🔴
**影响范围**: 18+ 个控制器，约 1400 行重复代码

**重复类型**:
1. **Form Schema 构建** (10 文件, ~200 行)
   - 位置: `create()` 和 `edit()` 方法
   - 文件: UserController, RoleController, PermissionController 等
   
2. **last_page 计算** (14 文件, ~150 行)
   - 位置: `listData()` 方法
   - 模式: `isset($result['page_size']) && $result['page_size'] > 0 ? (int) ceil(...) : 1`

3. **batchDestroy 方法** (12 文件, ~100 行)
   - 位置: 各个控制器
   - 问题: 大部分与基类实现重复

**解决方案**: 见 [重构计划](#-重构计划)

### **2. PHP 8.4 弃用警告** 🟡
**问题**: 隐式可空参数弃用警告
**位置**:
- `App\Controller\AbstractController::result()`
- `App\Model\Admin\AdminPermission::buildTree()`
- `Addons\AddonsStore\Service\AddonsStoreService::downloadAddon()`

**解决方案**: 将 `?Type $param = null` 改为显式可空声明

### **3. PostgreSQL zhparser 自动安装** 🟢
**状态**: 已实现
**行为**: 
- 检测 zhparser 未安装时自动尝试 `CREATE EXTENSION`
- 失败时显示警告，不影响安装流程
- 需要 SUPERUSER 权限或扩展已编译安装

---

## 📅 **重构计划**

### **阶段 1: 快速胜利（1 周，零风险）**

#### **任务 1.1: 创建 FormSchemaHelper Trait** ✅
```php
// 新建: app/Controller/Admin/Traits/FormSchemaHelper.php
trait FormSchemaHelper {
    protected function buildFormSchema(...): array { }
    protected function calculateLastPage(...): int { }
    protected function formatPaginatedResponse(...): array { }
}
```

**优势**:
- ✅ 只添加，不修改现有代码
- ✅ 向后兼容
- ✅ 可选使用

#### **任务 1.2: 试点迁移** 🔄
**顺序**:
1. PermissionController（低风险）
2. RoleController（低风险）
3. UserController（中风险，需充分测试）

**每个控制器都要**:
- ✅ 完整功能测试
- ✅ 回归测试
- ✅ 观察 1-2 天

### **阶段 2: 架构优化（2-3 周）**

#### **任务 2.1: 统一 AI Agent 服务**
- 为 AI Agent 相关服务添加 BaseService 继承
- 或创建 AiAgentBaseService

#### **任务 2.2: 审查控制器覆盖**
- 移除不必要的 `batchDestroy()` 覆盖
- 移除不必要的 `listData()` 覆盖

### **阶段 3: 持续改进**

#### **任务 3.1: CI/CD 集成**
```bash
# 添加到 CI 流程
vendor/bin/phpcpd --min-lines 5 --min-tokens 30 app/ || exit 1
```

#### **任务 3.2: 代码审查检查清单**
- [ ] 是否可以使用基类方法？
- [ ] 是否有重复的数据格式化？
- [ ] 是否正确利用了现有抽象？

---

## 📊 **代码统计**

### **项目规模**
- PHP 文件总数: 153
- 控制器文件: 40
- 代码重复: ~1400 行（可优化）
- 最大文件: `BaseModelCrudController.php` (1322 行)

### **代码质量指标**
- ✅ 已有基类抽象
- ✅ 清晰的分层架构
- 🟡 部分重复代码待优化
- 🟡 PHP 8.4 弃用警告待修复

---

## 🔐 **安全注意事项**

### **生产环境检查清单**
- [ ] 修改默认管理员密码
- [ ] 配置环境变量（不提交 .env）
- [ ] 关闭调试模式（APP_ENV=prod）
- [ ] 配置 HTTPS
- [ ] 设置文件权限
- [ ] 配置防火墙规则

### **敏感信息**
- ⚠️ `.env` 文件包含数据库密码
- ⚠️ `storage/logs/` 可能包含敏感日志
- ⚠️ 上传文件目录需要权限控制

---

## 📚 **相关文档**

### **项目文档**
- 安装指南: `storage/view/admin/install/index.blade.php`
- API 文档: 待补充
- 数据库设计: 待补充

### **外部资源**
- Hyperf 官方文档: https://hyperf.wiki
- Swoole 文档: https://www.swoole.co.uk
- PostgreSQL zhparser: https://github.com/amutu/zhparser

---

## 🔄 **更新日志**

### **2026-03-07**
- ✅ 添加 PostgreSQL 数据库检测功能
- ✅ 实现 zhparser 中文分词自动安装
- ✅ 完成代码重复检测分析
- ✅ 创建项目长期记忆文档
- ✅ 创建 PR: https://github.com/M0Yi/moyi-admin/pull/1

### **待办事项**
- [ ] 修复 PHP 8.4 弃用警告
- [ ] 实施重构计划阶段 1
- [ ] 添加单元测试覆盖
- [ ] 完善 API 文档

---

## 💡 **AI 代理注意事项**

### **读取此文档的时机**
1. **会话开始时** - 了解项目上下文
2. **修改代码前** - 了解架构和规范
3. **遇到问题时** - 查看已知问题列表
4. **规划任务时** - 参考重构计划

### **修改此文档的时机**
1. **完成重要功能** - 更新功能列表
2. **发现新问题** - 添加到已知问题
3. **改变架构** - 更新架构说明
4. **完成重构** - 更新代码统计

### **文档维护原则**
- 保持简洁但全面
- 及时更新重要变更
- 记录关键决策和原因
- 提供具体的代码示例和路径

---

## 🎯 **快速参考**

### **测试登录**
```
URL: http://127.0.0.1:6501/admin/dev/login
用户名: admin
密码: 123456
```

### **重启服务**
```bash
pkill -f hyperf
php bin/hyperf.php server:watch
```

### **清空重装**
```bash
mysql -u moyi -e "DROP DATABASE moyi_admin; CREATE DATABASE moyi_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
redis-cli FLUSHALL
rm -rf runtime/container
php bin/hyperf.php server:watch
```

### **查看日志**
```bash
tail -f runtime/logs/hyperf.log
```

---

**📌 提示**: 此文档应定期更新，保持与项目实际状态同步。建议每次重要变更后都更新此文档。
