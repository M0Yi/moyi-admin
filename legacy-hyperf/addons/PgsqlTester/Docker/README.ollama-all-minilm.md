# Ollama Embedding Service（all-MiniLM-L6-v2）

## 概述

预装 `all-MiniLM-L6-v2` 模型的 Ollama 镜像，384维向量，轻量快速。

**特点**：容器启动时自动拉取模型，构建更快。

## 文件

| 文件 | 说明 |
|------|------|
| `Dockerfile.ollama-all-minilm` | 镜像构建文件 |

## 使用

### 1. 构建镜像

```bash
cd addons/PgsqlTester/Docker
docker build -t moyi/moyi-admin:ollama-all-minilm -f Dockerfile.ollama-all-minilm .
```

### 2. 运行容器

```bash
docker run -d -p 11434:11434 --name moyi-ollama moyi/moyi-admin:ollama-all-minilm
```

### 3. 首次启动

首次运行会**自动拉取模型**（约 90MB），请耐心等待：

```bash
# 查看日志
docker logs -f moyi-ollama
```

### 4. 验证

```bash
curl http://localhost:11434/api/tags
```

输出示例：
```json
{
  "models": [
    {
      "name": "all-minilm:latest",
      "size": 94325176,
      "digest": "2d4d7f8..."
    }
  ]
}
```

### 5. 测试 Embedding

```bash
curl http://localhost:11434/api/embed \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"model": "all-minilm:latest", "input": "PostgreSQL 全文搜索"}'
```

## 模型信息

| 属性 | 值 |
|------|-----|
| 模型 | all-MiniLM-L6-v2 |
| 维度 | 384 |
| 大小 | ~90MB |
| 内存 | ~1GB |

## 停止/删除

```bash
# 停止容器
docker stop moyi-ollama

# 删除容器
docker rm moyi-ollama
```

## 常见问题

### 首次启动很慢？

首次启动需要下载模型（约 90MB），请检查网络连接。

### 模型已下载但启动时又下载？

模型持久化在 Docker volume 中，删除容器不会丢失模型。如需重新拉取：

```bash
docker rm moyi-ollama
docker run -d -p 11434:11434 --name moyi-ollama moyi/moyi-admin:ollama-all-minilm
```
