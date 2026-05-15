# API测试控制器使用说明

## 概述

已创建 `ApiTestController` 控制器用于测试PostgreSQL数据库连接和其他API功能。

## 实现方式

**重要**: 控制器使用Hyperf框架的标准数据库操作方法（`Hyperf\DbConnection\Db`），而不是直接读取配置或手动创建PDO连接。这样可以：

- 充分利用Hyperf的连接池管理
- 正确处理数据库事务
- 遵循框架的设计理念
- 获得更好的错误处理和性能

## 可用的API端点

### 1. 测试PostgreSQL连接
```
GET /api/test/pgsql/connection
```

**功能**: 使用Hyperf DB类测试PostgreSQL数据库的连接状态

**响应示例**:
```json
{
    "code": 200,
    "msg": "PostgreSQL数据库连接成功",
    "data": {
        "status": "connected",
        "database": "moyi",
        "host": "172.18.0.3",
        "port": "5432",
        "version": "PostgreSQL 15.3",
        "connection_time": "2024-01-21 12:00:00"
    }
}
```

### 2. 测试PostgreSQL查询
```
GET /api/test/pgsql/query
```

**功能**: 测试PostgreSQL数据库的基本查询功能

**响应示例**:
```json
{
    "code": 200,
    "msg": "PostgreSQL数据库查询测试成功",
    "data": {
        "version": "PostgreSQL 15.3",
        "tables_count": 5,
        "tables": ["users", "roles", "permissions", "logs"],
        "query_time": "2024-01-21 12:00:00"
    }
}
```

### 3. 测试所有数据库连接
```
GET /api/test/connections
```

**功能**: 同时测试MySQL和PostgreSQL连接状态

**响应示例**:
```json
{
    "code": 200,
    "msg": "数据库连接测试完成",
    "data": {
        "mysql": {
            "status": "success",
            "version": "8.0.33"
        },
        "postgresql": {
            "status": "success",
            "version": "15.3"
        }
    }
}
```

### 4. 获取数据库状态信息
```
GET /api/test/db-status
```

**功能**: 获取所有数据库的实际连接状态和信息

**响应示例**:
```json
{
    "code": 200,
    "msg": "数据库状态信息",
    "data": {
        "mysql": {
            "connection": "default",
            "database": "moyi",
            "version": "8.0.33",
            "status": "connected"
        },
        "postgresql": {
            "connection": "pgsql",
            "database": "moyi",
            "version": "PostgreSQL 15.3",
            "status": "connected"
        }
    }
}
```

## 错误响应格式

当数据库连接失败时，返回格式如下：

```json
{
    "code": 5001,  // 错误码
    "msg": "PostgreSQL数据库连接失败: 连接超时",
    "data": {
        "error_type": "PDOException",
        "error_code": "08006",
        "host": "postgres",
        "port": 5432,
        "database": "moyi"
    }
}
```

## 使用方法

1. **启动服务器**:
   ```bash
   php bin/hyperf.php start
   ```

2. **使用浏览器或API工具访问端点**:
   - 打开浏览器访问: `http://localhost:9501/api/test/pgsql/connection`
   - 或使用curl: `curl http://localhost:9501/api/test/pgsql/connection`

3. **检查响应**:
   - `code: 200` 表示成功
   - `code: 5001` 表示数据库连接错误
   - 其他错误码请查看 `ErrorCode` 常量

## 注意事项

1. 确保PostgreSQL服务正在运行
2. 检查数据库配置是否正确（`config/autoload/databases.php`）
3. 默认PostgreSQL配置：
   - 主机: `postgres`
   - 端口: `5432`
   - 数据库: `moyi`
   - 用户名: `postgres`
   - 密码: 空（可通过环境变量配置）

## 环境变量配置

可以通过环境变量配置数据库连接：

```bash
# PostgreSQL配置
PG_HOST=postgres
PG_PORT=5432
PG_DATABASE=moyi
PG_USERNAME=postgres
PG_PASSWORD=your_password
```

## 故障排除

1. **连接失败**: 检查PostgreSQL服务是否启动
2. **权限错误**: 检查数据库用户权限
3. **网络错误**: 检查主机名和端口配置
4. **扩展缺失**: 确保PHP已安装pdo_pgsql扩展
