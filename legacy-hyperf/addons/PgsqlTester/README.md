# PostgreSQL 测试插件 (PgsqlTester)

## 插件简介

PostgreSQL 测试插件是一个专门用于测试和监控 PostgreSQL 数据库连接、查询性能的 Moyi Admin 插件。它提供了全面的数据库测试功能，包括连接测试、查询测试、性能测试、表信息查看等。

## 功能特性

### 🔗 连接测试
- 测试 PostgreSQL 数据库连接状态
- 显示连接响应时间
- 获取数据库基本信息（版本、数据库名、用户等）

### 📝 查询测试
- 执行自定义 SQL 查询
- 支持参数化查询
- 显示查询执行时间和结果

### ⚡ 性能测试
- 批量执行查询测试性能
- 计算 QPS（每秒查询数）
- 统计平均、最小、最大响应时间

### 📊 表信息查看
- 查看数据库中的所有表
- 显示表大小和行数统计
- 查看索引信息和表结构

### 📋 测试日志记录
- 自动记录所有测试操作详情
- 包含测试类型、执行时间、结果状态
- 支持按用户、时间等条件查询

### 📈 统计数据分析
- 按日期统计各类测试执行情况
- 记录成功/失败次数和响应时间统计
- 提供最近7天的趋势数据

## 安装使用

### 1. 插件安装
将插件目录 `PgsqlTester` 放置在项目的 `addons` 目录下。

### 2. 依赖安装
确保已安装 PostgreSQL 数据库驱动：
```bash
composer require hyperf/database-pgsql
```

### 3. 数据库配置
在 `config/autoload/databases.php` 中配置 PostgreSQL 连接：

```php
'pgsql' => [
    'driver' => env('PG_DRIVER', 'pgsql'),
    'host' => env('PG_HOST', 'postgres'),
    'port' => env('PG_PORT', 5432),
    'database' => env('PG_DATABASE', 'moyi'),
    'username' => env('PG_USERNAME', 'postgres'),
    'password' => env('PG_PASSWORD', ''),
    'charset' => env('PG_CHARSET', 'utf8'),
    'prefix' => env('PG_PREFIX', ''),
    // ... 其他配置
]
```

### 4. 环境变量配置
在 `.env` 文件中添加 PostgreSQL 配置：

```env
# PostgreSQL 数据库配置
PG_DRIVER=pgsql
PG_HOST=postgres
PG_PORT=5432
PG_DATABASE=moyi
PG_USERNAME=postgres
PG_PASSWORD=your_password
PG_CHARSET=utf8
PG_PREFIX=
```

### 5. 插件启用
通过 Moyi Admin 的插件管理界面启用插件。

**注意：** 插件本身不负责数据库表创建，请确保在使用前已创建所需的数据库表：
- `pgsql_tester_test_logs`：存储所有测试操作的详细日志
- `pgsql_tester_statistics`：按日期统计测试数据和性能指标

可以使用数据库迁移或其他方式创建这些表。

### 6. 配置说明

插件提供了丰富的配置选项，可以通过管理界面进行设置：

#### 基本配置
- **插件显示名称**：在界面上显示的名称
- **启用详细日志**：是否记录每次测试的详细信息
- **启用性能监控**：是否收集性能统计数据
- **日志保留天数**：测试日志的保留时间
- **启用实时统计**：是否显示实时统计信息

#### 测试参数配置
- **默认性能测试次数**：性能测试的默认迭代次数
- **默认性能测试查询**：性能测试使用的默认SQL
- **连接超时时间**：数据库连接的超时时间
- **查询超时时间**：SQL查询的超时时间

#### 安全配置
- **启用速率限制**：是否限制用户测试频率
- **每分钟最大测试数**：每个用户每分钟允许的最大测试次数
- **允许的测试用户角色**：限制只有特定角色的用户可以测试
- **启用IP白名单**：限制只有特定IP可以执行测试

#### 配置方法
1. 进入插件管理页面
2. 找到 PgsqlTester 插件
3. 点击"配置"按钮
4. 根据需要调整各项配置
5. 保存配置后立即生效

## 使用说明

### 访问入口
插件安装启用后，可以通过以下路径访问：

- **仪表盘**: `/admin/{adminPath}/pgsql_tester/dashboard`
- **连接测试**: `/admin/{adminPath}/pgsql_tester/connection-test`
- **查询测试**: `/admin/{adminPath}/pgsql_tester/query-test`
- **性能测试**: `/admin/{adminPath}/pgsql_tester/performance-test`
- **表信息**: `/admin/{adminPath}/pgsql_tester/table-info`

### API 接口
插件提供了丰富的 API 接口：

```javascript
// 连接测试
GET /api/pgsql_tester/connection

// 查询测试
POST /api/pgsql_tester/query
{
    "query": "SELECT version()",
    "params": []
}

// 性能测试
POST /api/pgsql_tester/performance
{
    "iterations": 100,
    "query": "SELECT 1"
}

// 数据库信息
GET /api/pgsql_tester/info

// 表信息
GET /api/pgsql_tester/tables

// 扩展信息
GET /api/pgsql_tester/extensions

// 统计信息
GET /api/pgsql_tester/stats
```

## 插件架构

### 目录结构
```
addons/PgsqlTester/
├── Controller/           # 控制器
│   ├── Admin/           # 后台管理控制器
│   └── Api/             # API 接口控制器
├── Service/             # 业务服务层
├── Model/               # 数据模型（预留）
├── View/                # 视图文件
│   └── admin/           # 后台视图
├── Public/              # 静态资源
│   ├── css/            # 样式文件
│   └── js/             # JavaScript 文件
├── Manager/             # 插件管理配置
├── info.php            # 插件信息
├── routes.php          # 路由配置
└── README.md           # 说明文档
```

### 核心文件说明

- **`info.php`**: 插件基本信息配置
- **`routes.php`**: 路由定义文件
- **`PgsqlTesterService.php`**: 核心业务服务类
- **`PgsqlTesterController.php`**: 后台管理控制器
- **`PgsqlTesterApiController.php`**: API 接口控制器
- **`menus_permissions.json`**: 菜单和权限配置

## 开发扩展

### 添加新的测试类型
在 `PgsqlTesterService` 中添加新的测试方法：

```php
public function customTest(): array
{
    try {
        $pgsql = Db::connection('pgsql');
        // 自定义测试逻辑
        return ['status' => 'success', 'data' => $result];
    } catch (Throwable $e) {
        return ['status' => 'error', 'error' => $e->getMessage()];
    }
}
```

### 自定义视图
在 `View/admin/` 目录下添加新的视图文件，并更新路由和控制器。

### 扩展 API 接口
在 `PgsqlTesterApiController` 中添加新的 API 方法，并更新路由配置。

## 注意事项

1. **依赖要求**: 需要 Swoole 5.1.0+ 并开启 `--enable-swoole-pgsql`
2. **权限控制**: 插件实现了完整的权限控制，确保只有授权用户才能访问
3. **性能监控**: 插件会自动记录测试统计信息，便于监控数据库性能
4. **错误处理**: 所有操作都有完善的错误处理机制
5. **数据安全**: 查询测试支持参数化查询，防止 SQL 注入

## 技术栈

- **框架**: Hyperf 3.1+
- **数据库**: PostgreSQL 9.6+
- **前端**: Blade 模板 + jQuery + Bootstrap
- **缓存**: Redis（可选，用于统计信息存储）

## 更新日志

### v1.0.0 (2024-01-XX)
- 初始版本发布
- 实现基础的连接测试、查询测试、性能测试功能
- 提供完整的表信息查看功能
- 支持统计信息记录和展示

## 许可证

本插件遵循 Apache 2.0 许可证。
