# 多阶段构建：构建阶段 + 运行阶段
# Stage 1: 构建前端应用
FROM node:20-alpine AS builder

LABEL maintainer="moyi <moyi@moyi.vip>" version="1.0" app.name="moyi-admin-frontend"

WORKDIR /app

# 复制 package 文件
COPY package*.json ./

# 安装所有依赖（包括 devDependencies，构建需要）
RUN npm ci && npm cache clean --force

# 复制源代码
COPY . .

# 构建生产版本
RUN npm run build

# Stage 2: 使用 Nginx 提供静态文件服务
FROM nginx:alpine

# 安装 tzdata 设置时区
RUN apk add --no-cache tzdata && \
    cp /usr/share/zoneinfo/Asia/Shanghai /etc/localtime && \
    echo "Asia/Shanghai" > /etc/timezone && \
    apk del tzdata

# 删除默认的 nginx 配置
RUN rm /etc/nginx/conf.d/default.conf

# 复制自定义 nginx 配置
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# 从构建阶段复制构建产物
COPY --from=builder /app/dist /usr/share/nginx/html

# 暴露端口
EXPOSE 80

# 启动 nginx
CMD ["nginx", "-g", "daemon off;"]
