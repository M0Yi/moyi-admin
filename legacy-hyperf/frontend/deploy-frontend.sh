#!/bin/bash

# ============================================
# 建辉慈善前台 - 一键部署脚本
# ============================================

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印带颜色的消息
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# ============================================
# 配置部分
# ============================================

# 容器配置
FRONTEND_IMAGE_NAME="moyi-admin-frontend"
FRONTEND_CONTAINER_NAME="moyi-admin-frontend"
FRONTEND_PORT="${FRONTEND_PORT:-80}"

# 后端API配置（前端通过nginx代理访问后端）
BACKEND_API_URL="${BACKEND_API_URL:-http://localhost:6501}"

# Docker配置
DOCKER_REGISTRY=""  # 如果使用私有仓库，设置 registry 地址
COMPOSE_FILE="docker-compose.frontend.yml"

# ============================================
# 检查Docker环境
# ============================================

check_docker() {
    print_message "$BLUE" "检查 Docker 环境..."
    if ! command -v docker &> /dev/null; then
        print_message "$RED" "❌ Docker 未安装，请先安装 Docker"
        exit 1
    fi
    print_message "$GREEN" "✅ Docker 已安装: $(docker --version | head -1)"
}

check_docker_compose() {
    print_message "$BLUE" "检查 Docker Compose..."
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_message "$RED" "❌ Docker Compose 未安装，请先安装 Docker Compose"
        exit 1
    fi
    if command -v docker-compose &> /dev/null; then
        print_message "$GREEN" "✅ Docker Compose 已安装: $(docker-compose --version | head -1)"
    else
        print_message "$GREEN" "✅ Docker Compose 已安装: $(docker compose version | head -1)"
    fi
}

# ============================================
# 检查后端服务
# ============================================

check_backend() {
    print_message "$BLUE" "检查后端服务..."
    print_message "$BLUE" "  后端地址: $BACKEND_API_URL"

    # 提取主机和端口
    local backend_host=$(echo "$BACKEND_API_URL" | sed -E 's|https?://([^/:]+).*|\1|')
    local backend_port=$(echo "$BACKEND_API_URL" | sed -E 's|https?://[^:]+:([0-9]+).*|\1|')

    # 如果没有指定端口，使用默认端口
    if [ "$backend_port" = "$BACKEND_API_URL" ]; then
        if echo "$BACKEND_API_URL" | grep -q "https"; then
            backend_port="443"
        else
            backend_port="80"
        fi
    fi

    # 尝试连接后端，最多等待5秒
    local max_attempts=5
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        if command -v curl &> /dev/null; then
            # 使用 curl 检查
            if curl -s -o /dev/null -w "%{http_code}" --connect-timeout 3 --max-time 5 \
                "$BACKEND_API_URL" 2>/dev/null | grep -qE "^(200|301|302|404)$"; then
                print_message "$GREEN" "✅ 后端服务连接正常"
                return 0
            fi
        elif command -v wget &> /dev/null; then
            # 使用 wget 检查
            if wget -q --timeout=5 --tries=1 -O /dev/null \
                "$BACKEND_API_URL" 2>/dev/null; then
                print_message "$GREEN" "✅ 后端服务连接正常"
                return 0
            fi
        elif command -v nc &> /dev/null; then
            # 使用 nc 检查端口
            if nc -z -w 3 "$backend_host" "$backend_port" 2>/dev/null; then
                print_message "$GREEN" "✅ 后端服务端口可访问"
                return 0
            fi
        fi

        if [ $attempt -lt $max_attempts ]; then
            print_message "$YELLOW" "  第 $attempt 次连接失败，重试..."
            sleep 1
        fi
        attempt=$((attempt + 1))
    done

    print_message "$RED" "❌ 无法连接到后端服务: $BACKEND_API_URL"
    print_message "$YELLOW" "请确认："
    print_message "$YELLOW" "  1. 后端服务是否正在运行"
    print_message "$YELLOW" "  2. 后端地址是否正确"
    print_message "$YELLOW" "  3. 防火墙是否允许访问"
    print_message "$YELLOW" ""
    print_message "$YELLOW" "如需跳过后端检查，请使用 --skip-backend-check 参数"

    return 1
}

# ============================================
# 使用说明
# ============================================

show_usage() {
    cat << EOF
用法: $0 [选项]

前台一键部署脚本 - 单独部署前端服务

选项:
  -h, --help              显示此帮助信息
  -r, --rebuild          强制重新构建镜像
  -d, --detach          后台运行部署（不查看日志）
  -p, --port PORT        指定前端端口（默认：80）
  -b, --backend URL      指定后端API地址（默认：http://localhost:6501）
  -l, --logs             部署后查看日志
  --skip-backend-check   跳过后端服务检查
  --stop                 停止并删除前端容器
  --restart              重启前端容器

环境变量:
  FRONTEND_PORT          前端服务端口（默认：80）
  BACKEND_API_URL        后端API地址（默认：http://localhost:6501）

示例:
  # 基础部署（端口80）
  $0

  # 指定端口部署
  $0 -p 8080

  # 指定后端API地址
  $0 -b http://192.168.1.100:6501

  # 强制重新构建
  $0 -r

  # 部署后查看日志
  $0 -l

  # 跳过后端检查
  $0 --skip-backend-check

  # 停止服务
  $0 --stop

  # 重启服务
  $0 --restart

EOF
}

# ============================================
# 解析命令行参数
# ============================================

REBUILD=false
DETACH=false
VIEW_LOGS=false
STOP_SERVICE=false
RESTART_SERVICE=false
SHOW_HELP=false
SKIP_BACKEND_CHECK=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            SHOW_HELP=true
            shift
            ;;
        -r|--rebuild)
            REBUILD=true
            shift
            ;;
        -d|--detach)
            DETACH=true
            shift
            ;;
        -p|--port)
            FRONTEND_PORT="$2"
            shift 2
            ;;
        -b|--backend)
            BACKEND_API_URL="$2"
            shift 2
            ;;
        -l|--logs)
            VIEW_LOGS=true
            shift
            ;;
        --stop)
            STOP_SERVICE=true
            shift
            ;;
        --restart)
            RESTART_SERVICE=true
            shift
            ;;
        --skip-backend-check)
            SKIP_BACKEND_CHECK=true
            shift
            ;;
        *)
            print_message "$RED" "未知选项: $1"
            echo "使用 --help 查看帮助"
            exit 1
            ;;
    esac
done

# 显示帮助
if [ "$SHOW_HELP" = true ]; then
    show_usage
    exit 0
fi

# ============================================
# 函数定义
# ============================================

# 停止服务
stop_service() {
    print_message "$YELLOW" "停止前端服务..."

    cd "$(dirname "$0")"

    if [ -n "$FRONTEND_CONTAINER_NAME" ]; then
        # 停止并删除容器
        if docker ps --filter "name=$FRONTEND_CONTAINER_NAME" --format "{{.Names}}" | grep -q "$FRONTEND_CONTAINER_NAME"; then
            docker stop "$FRONTEND_CONTAINER_NAME" 2>/dev/null
            print_message "$GREEN" "  容器已停止"
        fi

        # 删除容器
        if docker ps -a --filter "name=$FRONTEND_CONTAINER_NAME" --format "{{.Names}}" | grep -q "$FRONTEND_CONTAINER_NAME"; then
            docker rm "$FRONTEND_CONTAINER_NAME" 2>/dev/null
            print_message "$GREEN" "  容器已删除"
        fi
    fi

    print_message "$GREEN" "✅ 前端服务已停止"
}

# 重启服务
restart_service() {
    print_message "$YELLOW" "重启前端服务..."
    stop_service
    build_and_deploy
}

# 构建镜像
build_image() {
    print_message "$YELLOW" "构建前端镜像..."
    cd "$(dirname "$0")"

    if [ "$REBUILD" = true ]; then
        print_message "$YELLOW" "强制重新构建（不使用缓存）..."
        docker build --no-cache -t "$FRONTEND_IMAGE_NAME" .
    else
        print_message "$YELLOW" "构建前端镜像..."
        docker build -t "$FRONTEND_IMAGE_NAME" .
    fi

    print_message "$GREEN" "✅ 镜像构建完成"
}

# 部署服务
deploy_service() {
    print_message "$YELLOW" "部署前端服务..."
    cd "$(dirname "$0")"

    # 创建临时 docker-compose 文件
    cat > docker-compose.frontend.yml << EOF
services:
  moyi-admin-frontend:
    container_name: ${FRONTEND_CONTAINER_NAME}
    image: ${FRONTEND_IMAGE_NAME}
    ports:
      - "${FRONTEND_PORT}:80"
    environment:
      - BACKEND_API_URL=${BACKEND_API_URL}
    restart: unless-stopped
    networks:
      - moyi-frontend

networks:
  moyi-frontend:
    external: false
EOF

    # 启动服务
    if command -v docker-compose &> /dev/null; then
        docker-compose -f "$COMPOSE_FILE" up -d
    else
        docker compose -f "$COMPOSE_FILE" up -d
    fi

    print_message "$GREEN" "✅ 前端服务已启动"
}

# 查看日志
view_logs() {
    print_message "$YELLOW" "查看前端服务日志..."
    cd "$(dirname "$0")"

    if [ -n "$FRONTEND_CONTAINER_NAME" ]; then
        if command -v docker-compose &> /dev/null; then
            docker-compose -f "$COMPOSE_FILE" logs -f --tail=50 "$FRONTEND_CONTAINER_NAME"
        else
            docker compose -f "$COMPOSE_FILE" logs -f --tail=50 "$FRONTEND_CONTAINER_NAME"
        fi
    fi
}

# 检查服务状态
check_service() {
    print_message "$BLUE" "检查服务状态..."
    sleep 2

    if docker ps --filter "name=$FRONTEND_CONTAINER_NAME" --format "{{.Names}}" | grep -q "$FRONTEND_CONTAINER_NAME"; then
        print_message "$GREEN" "✅ 前端容器运行正常"
    else
        print_message "$RED" "❌ 前端容器启动失败"
        print_message "$YELLOW" "查看日志："
        docker logs "$FRONTEND_CONTAINER_NAME"
        exit 1
    fi
}

# 构建和部署
build_and_deploy() {
    build_image
    deploy_service
    check_service
}

# ============================================
# 主函数
# ============================================

main() {
    print_message "$BLUE" "========================================"
    print_message "$BLUE" "   建辉慈善前台 - 一键部署脚本"
    print_message "$BLUE" "========================================"
    echo ""

    # 显示配置信息
    print_message "$BLUE" "部署配置："
    echo "  🌐 前端端口: $FRONTEND_PORT"
    echo "  📡 后端API: $BACKEND_API_URL"
    echo "  🔧 镜像名称: $FRONTEND_IMAGE_NAME"
    echo "  📦 容器名称: $FRONTEND_CONTAINER_NAME"
    echo ""

    # 停止服务
    if [ "$STOP_SERVICE" = true ]; then
        stop_service
        exit 0
    fi

    # 重启服务
    if [ "$RESTART_SERVICE" = true ]; then
        restart_service
        exit 0
    fi

    # 检查后端服务（除非跳过）
    if [ "$SKIP_BACKEND_CHECK" = false ]; then
        if ! check_backend; then
            print_message "$RED" "❌ 后端服务检查失败，部署已终止"
            exit 1
        fi
    else
        print_message "$YELLOW" "⚠️  跳过后端服务检查"
    fi

    # 执行部署
    build_and_deploy

    # 显示访问信息
    echo ""
    print_message "$BLUE" "========================================"
    print_message "$GREEN" "🎉 部署完成！"
    print_message "$BLUE" "========================================"
    echo ""
    print_message "$BLUE" "访问地址："
    print_message "$GREEN" "  🌐 前端访问: http://localhost:$FRONTEND_PORT"
    print_message "$GREEN" "  📡 后端API: $BACKEND_API_URL"
    echo ""
    print_message "$YELLOW" "常用命令："
    echo "  查看容器: docker ps"
    echo "  查看日志: docker logs $FRONTEND_CONTAINER_NAME"
    echo "  停止服务: $0 --stop"
    echo "  重启服务: $0 --restart"
    echo "  查看日志: $0 -l"
    echo ""

    # 查看日志
    if [ "$VIEW_LOGS" = true ]; then
        view_logs
    fi
}

# ============================================
# 执行
# ============================================

check_docker
check_docker_compose
main "$@"
