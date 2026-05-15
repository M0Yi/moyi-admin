# 建辉慈善后台 - Docker 部署文档

## 📋 部署架构

```
┌─────────────────────────────────────────────────────────┐
│                       用户浏览器                         │
└─────────────────────┬───────────────────────────────────┘
                      │
                      │ HTTP:80
                      ▼
┌─────────────────────────────────────────────────────────┐
│  前端容器 (moyi-admin-frontend)                         │
│  - Nginx + Vue 3 静态文件                               │
│  - 端口: 80                                              │
│  - API 代理: /api -> moyi-admin:6501                    │
└─────────────────────┬───────────────────────────────────┘
                      │
                      │ Docker 内部网络
                      ▼
┌─────────────────────────────────────────────────────────┐
│  后端容器 (moyi-admin)                                  │
│  - Hyperf PHP 框架                                      │
│  - 端口: 6501                                           │
│  - 连接 Redis + MySQL                                   │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        ▼                           ▼
┌──────────────┐          ┌──────────────┐
│   Redis      │          │   MySQL      │
│   端口: 6379 │          │   端口: 3306 │
└──────────────┘          └──────────────┘
```

## 🚀 快速开始

### 1. 一键部署（推荐）

```bash
# 在项目根目录执行
./deploy.sh
```

部署完成后：
- 🌐 前端访问地址: http://localhost
- 📡 后端 API 地址: http://localhost:6501

### 2. 高级选项

```bash
# 强制重新构建镜像
./deploy.sh --rebuild

# 部署后查看日志
./deploy.sh --logs

# 查看帮助
./deploy.sh --help
```

## 📦 部署内容

### 前端容器
- **镜像**: moyi-admin-frontend
- **容器名**: moyi-admin-frontend
- **技术栈**: Vue 3 + Vite + Nginx
- **端口**: 80 (可修改)
- **功能**:
  - 提供静态文件服务
  - API 反向代理到后端
  - 支持 Vue Router History 模式
  - Gzip 压缩
  - 静态资源缓存

### 后端容器
- **镜像**: moyi-admin
- **容器名**: moyi-admin
- **技术栈**: Hyperf + PHP 8.3 + Swoole
- **端口**: 6501
- **功能**:
  - RESTful API 服务
  - 业务逻辑处理
  - 数据持久化

### 数据库
- **Redis**: 缓存服务
- **MySQL**: 数据存储（MariaDB 11）

## 🔧 配置说明

### 修改前端端口

编辑 `docker-compose.yml`:

```yaml
moyi-admin-frontend:
  ports:
    - "8080:80"  # 修改为其他端口，如 8080
```

### 修改后端配置

后端配置文件在项目根目录的 `config/` 目录下，主要配置：
- `config/database.php` - 数据库配置
- `config/redis.php` - Redis 配置
- `.env` - 环境变量

### 修改 Nginx 配置

编辑 `frontend/docker/nginx.conf`，可以修改：
- 反向代理设置
- 静态资源缓存策略
- CORS 配置
- Gzip 压缩设置

## 📝 常用命令

### 容器管理

```bash
# 查看所有容器状态
docker ps

# 查看容器日志
docker logs moyi-admin-frontend  # 前端日志
docker logs moyi-admin           # 后端日志

# 进入容器
docker exec -it moyi-admin-frontend sh
docker exec -it moyi-admin bash

# 重启服务
docker-compose restart

# 停止所有服务
docker-compose down

# 停止并删除数据卷（⚠️ 会删除数据）
docker-compose down -v
```

### 查看日志

```bash
# 查看所有服务日志
docker-compose logs -f

# 查看指定服务日志
docker-compose logs -f moyi-admin-frontend
docker-compose logs -f moyi-admin

# 查看最近 100 行日志
docker-compose logs --tail=100
```

### 数据备份

```bash
# 备份 MySQL 数据
docker exec mysql mysqldump -uroot -p moyi_db > backup.sql

# 恢复 MySQL 数据
docker exec -i mysql mysql -uroot -p moyi_db < backup.sql

# 备份 Redis 数据
docker exec redis redis-cli --rdb /data/backup.rdb
docker cp redis:/data/backup.rdb ./redis-backup.rdb
```

## 🐛 故障排查

### 前端无法访问

1. 检查容器状态:
```bash
docker ps | grep moyi-admin-frontend
```

2. 查看前端日志:
```bash
docker logs moyi-admin-frontend
```

3. 检查 Nginx 配置:
```bash
docker exec moyi-admin-frontend cat /etc/nginx/conf.d/default.conf
```

### API 请求失败

1. 检查后端容器状态:
```bash
docker ps | grep moyi-admin
```

2. 查看后端日志:
```bash
docker logs moyi-admin
```

3. 测试后端服务:
```bash
curl http://localhost:6501
```

### 数据库连接失败

1. 检查 MySQL 容器:
```bash
docker ps | grep mysql
```

2. 查看 MySQL 日志:
```bash
docker logs mysql
```

3. 进入数据库测试:
```bash
docker exec -it mysql mysql -uroot -p
```

## 🔒 生产环境部署建议

### 1. 安全加固

- 修改数据库默认密码
- 限制容器网络访问
- 使用 HTTPS（配置 SSL 证书）
- 设置防火墙规则

### 2. 性能优化

- 调整 PHP-FPM 配置
- 配置 Redis 持久化
- 使用 CDN 加速静态资源
- 启用 Nginx 缓存

### 3. 监控告警

- 配置容器健康检查
- 设置日志收集
- 监控资源使用
- 配置告警通知

### 4. 备份策略

- 定期备份数据库
- 备份上传文件
- 备份配置文件
- 测试恢复流程

## 📚 更多信息

- [Docker 官方文档](https://docs.docker.com/)
- [Docker Compose 文档](https://docs.docker.com/compose/)
- [Nginx 文档](https://nginx.org/en/docs/)
- [Hyperf 文档](https://hyperf.wiki/)
- [Vue 3 文档](https://vuejs.org/)

## 🆘 获取帮助

如遇到问题：
1. 查看本文档的故障排查部分
2. 检查容器日志
3. 提交 Issue 到项目仓库
