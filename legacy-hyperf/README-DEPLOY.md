# 🚀 建辉慈善后台 - Docker 一键部署

## 快速部署

### 首次部署（构建镜像）

```bash
./deploy.sh
```

### 日常启动（已有镜像）

```bash
./start.sh
```

## 访问地址

- 🌐 **前端**: http://localhost
- 📡 **后端 API**: http://localhost:6501

## 常用命令

```bash
# 查看所有容器
docker ps

# 查看日志
docker-compose logs -f

# 停止服务
docker-compose down

# 重启服务
docker-compose restart

# 重新构建并部署
./deploy.sh --rebuild
```

## 架构说明

```
前端 (Nginx + Vue3) :80
    ↓ API 代理
后端 (Hyperf + PHP) :6501
    ↓
Redis + MySQL
```

## 详细文档

查看 [DEPLOY.md](./DEPLOY.md) 获取完整部署文档。

## 故障排查

```bash
# 查看前端日志
docker logs moyi-admin-frontend

# 查看后端日志
docker logs moyi-admin

# 进入容器
docker exec -it moyi-admin-frontend sh
docker exec -it moyi-admin bash
```

## 端口说明

- **80**: 前端 Nginx 服务（可在 docker-compose.yml 中修改）
- **6501**: 后端 API 服务
- **6379**: Redis（仅容器内部访问）
- **3306**: MySQL（仅容器内部访问）

## 数据持久化

- Redis 数据: `redis-data` volume
- MySQL 数据: `mysql-data` volume

## ⚠️ 重要提示

1. 首次部署需要构建镜像，可能需要几分钟
2. 确保端口 80 和 6501 未被占用
3. 生产环境请修改数据库默认密码
4. 建议定期备份 MySQL 数据
