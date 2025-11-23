# Moyi Admin - 基于 Hyperf 的后台管理系统

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-8.1+-blue.svg)
![Hyperf Version](https://img.shields.io/badge/Hyperf-3.1-green.svg)
![License](https://img.shields.io/badge/License-Apache--2.0-yellow.svg)

一个基于 Hyperf 框架构建的后台管理系统，采用通用 CRUD 设计模式，大幅减少重复代码。

[功能特性](#功能特性) • [技术栈](#技术栈) • [快速开始](#快速开始)

</div>

---

## 📖 项目简介

Moyi Admin 是一个基于 Hyperf 3.1 框架开发的后台管理系统，采用通用 CRUD 设计模式，通过配置即可完成数据管理功能，无需为每个模型重复编写代码。系统支持多数据库管理，在创建 CRUD 配置和进行数据操作时，可以选择不同的数据库连接，实现跨数据库的统一管理。

> **🌟 重要说明**：本项目完全基于时下最先进的 AI 技术构造开发，从架构设计、代码实现到文档编写，全程采用 AI 辅助开发，展现了 AI 在软件开发领域的强大能力。

### 核心特性

- 🤖 **AI 驱动开发**：完全基于最先进的 AI 技术构造，展现 AI 辅助开发的无限可能
- 🚀 **高性能**：基于 Swoole 协程，支持高并发处理
- 🎯 **通用 CRUD**：一套代码管理所有数据模型，无需重复开发
- 🗄️ **多数据库支持**：在 CRUD 操作时可选择不同的数据库进行数据管理
- 🗑️ **回收站**：支持软删除、恢复、永久删除
- 📊 **数据导出**：支持 Excel/CSV 格式导出
- 🎨 **现代化 UI**：基于 Bootstrap 5 的响应式界面

---

## ✨ 功能特性

### 已完成功能

#### 1. 核心 CRUD 功能
- ✅ **数据列表**：支持分页、搜索、排序
- ✅ **数据创建**：表单验证、字段类型自动识别，支持选择不同数据库进行创建
- ✅ **数据编辑**：支持数据更新、数据回显，支持跨数据库操作
- ✅ **数据删除**：支持单个删除、批量删除
- ✅ **状态切换**：快速启用/禁用状态切换
- ✅ **字段显示控制**：支持列显示/隐藏切换
- ✅ **多数据库管理**：在 CRUD 配置和操作时可选择不同的数据库连接

#### 2. 数据导出功能
- ✅ **Excel 导出**：支持导出为 Excel 格式
- ✅ **CSV 导出**：支持导出为 CSV 格式
- ✅ **条件导出**：支持按搜索条件导出数据

#### 3. 回收站功能
- ✅ **软删除**：删除数据进入回收站，不直接删除
- ✅ **回收站管理**：查看已删除数据列表
- ✅ **数据恢复**：支持单个恢复、批量恢复
- ✅ **永久删除**：支持单个永久删除、批量永久删除
- ✅ **清空回收站**：一键清空所有已删除数据

#### 4. CRUD 配置管理
- ✅ **CRUD 配置**：可视化配置数据表的 CRUD 功能
- ✅ **字段配置**：可视化配置字段类型、验证规则、显示方式
- ✅ **功能开关**：支持搜索、新增、编辑、删除、导出等功能开关

#### 5. 系统功能
- ✅ **登录认证**：用户登录认证
- ✅ **文件上传**：支持图片、文件上传
- ✅ **仪表盘**：系统首页
- ✅ **系统安装**：可视化系统安装向导（自动创建数据库表）

---

## 🛠️ 技术栈

### 后端技术
- **框架**：Hyperf 3.1（基于 Swoole 协程框架）
- **语言**：PHP 8.1+
- **数据库**：MySQL 5.7+
- **缓存**：Redis
- **ORM**：Hyperf Database（类似 Eloquent）

### 前端技术
- **UI 框架**：Bootstrap 5.3
- **图标库**：Bootstrap Icons
- **模板引擎**：Blade（Hyperf View Engine）
- **JavaScript**：原生 ES6+（无框架依赖）
- **表格组件**：自定义 DataTable 组件
- **日期选择**：Flatpickr
- **下拉选择**：Tom Select

---

## 🚀 快速开始

### 环境要求

- PHP >= 8.1
- Swoole >= 5.0
- MySQL >= 5.7
- Redis >= 5.0
- Composer >= 2.0

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/your-username/moyi-admin.git
cd moyi-admin
```

#### 2. 安装依赖

```bash
composer install
```

#### 3. 配置环境

复制环境配置文件：

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置数据库和 Redis：

```env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=moyi_admin
DB_USERNAME=root
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
```

**💡 多数据库配置**：

系统支持管理多个数据库。如需配置多个数据库连接，请编辑 `config/autoload/databases.php` 文件：

```php
return [
    'default' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'moyi_admin'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        // ... 其他配置
    ],
    // 添加更多数据库连接
    'database2' => [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'another_database',
        'username' => 'root',
        'password' => 'password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        // ... 其他配置
    ],
    // 可以继续添加更多数据库连接
];
```

配置完成后，系统会自动识别所有数据库连接，您可以在后台管理界面中切换不同的数据库进行管理。

#### 4. 启动服务

**开发环境**：

```bash
php bin/hyperf.php start
```

**使用 Docker**：

```bash
docker-compose up -d
```

> **⚠️ 重要提示**：使用 Docker 时，需要确保主机端口 6501 未被占用。如果 6501 端口已被占用，可以修改 `docker-compose.yml` 中的端口映射，例如改为 `6601:6501`（将主机 6601 映射到容器 6501），然后通过 `http://localhost:6601` 访问。

#### 5. 初始化系统

启动服务后，访问安装页面进行系统初始化：

```
http://localhost:6501/install
```

安装程序会自动创建数据库表并初始化系统数据，无需手动运行迁移命令。初始化完成后，系统会显示后台登录地址和账号信息。

---

## 📸 项目截图

> 💡 **提示**：以下位置用于放置项目截图，您可以将后台管理系统的截图放在对应位置。

### 登录页面

![登录页面](./docs/images/login.png)

*登录页面截图*

### 仪表盘

![仪表盘](./docs/images/dashboard.png)

*系统仪表盘，展示数据统计和图表*

### 数据列表

![数据列表](./docs/images/list.png)

*通用 CRUD 列表页面，支持搜索、筛选、分页*

### 数据编辑

![数据编辑](./docs/images/edit.png)

*数据编辑页面，支持多种字段类型*

### 回收站

![回收站](./docs/images/trash.png)

*回收站管理页面，支持恢复和永久删除*

### CRUD 配置

![CRUD 配置](./docs/images/crud-config.png)

*CRUD 配置页面，可视化配置数据表的 CRUD 功能和字段属性*

### CRUD 列表页

![CRUD 列表页](./docs/images/crud-list.png)

*CRUD 列表页面，展示通过配置生成的数据管理界面*

### CRUD 数据库选择页

![CRUD 数据库选择](./docs/images/crud-database-select.png)

*CRUD 数据库选择页面，支持选择不同的数据库连接进行数据管理*


---

## 🎯 项目特点

### 1. 通用 CRUD 设计
- **零代码开发**：通过配置即可完成 CRUD 功能
- **统一接口**：所有模型使用统一的 CRUD 接口
- **自动识别**：自动识别数据库表结构和字段类型
- **灵活配置**：支持字段显示、验证、格式化等配置

### 2. 高性能架构
- **协程支持**：基于 Swoole 协程，支持高并发处理
- **连接池**：数据库和 Redis 连接池，减少连接开销

### 3. 用户体验
- **响应式设计**：支持 PC 和移动端访问
- **现代化 UI**：基于 Bootstrap 5 的美观界面
- **操作便捷**：批量操作、快捷操作等提升效率

---

## 📁 项目结构

```
moyi-admin/
├── app/                    # 应用目录
│   ├── Constants/          # 常量定义
│   ├── Controller/         # 控制器
│   │   └── Admin/         # 后台控制器
│   ├── Exception/         # 异常处理
│   ├── Middleware/        # 中间件
│   ├── Model/             # 数据模型
│   │   └── Admin/         # 后台模型
│   ├── Service/           # 业务逻辑层
│   │   └── Admin/         # 后台服务
│   └── Support/           # 支持类
├── config/                 # 配置文件
│   └── autoload/          # 自动加载配置
├── storage/                # 存储目录
│   ├── view/              # Blade 视图文件
│   ├── logs/              # 日志文件
│   └── app/               # 应用文件
├── public/                 # 公共资源
│   ├── css/               # 样式文件
│   ├── js/                # JavaScript 文件
│   └── uploads/           # 上传文件
├── runtime/                # 运行时文件
├── test/                   # 测试文件
├── docs/                   # 文档目录
└── vendor/                 # 依赖包
```

---

## 🙏 致谢

- [Hyperf](https://www.hyperf.io/) - 高性能 PHP 协程框架
- [Swoole](https://www.swoole.com/) - 高性能异步网络通信引擎
- [Bootstrap](https://getbootstrap.com/) - 前端 UI 框架
- [FastAdmin](https://www.fastadmin.net/) - 基于 ThinkPHP 和 Bootstrap 的极速后台开发框架
- 所有为本项目做出贡献的开发者

---

<div align="center">

**如果这个项目对你有帮助，请给一个 ⭐ Star！**

Made with ❤️ by Moyi Team

</div>
