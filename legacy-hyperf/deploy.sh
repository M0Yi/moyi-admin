#!/bin/bash

# ============================================
# 建辉慈善后台一键部署脚本
# ============================================

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================
# 配置部分
# ============================================

# 容器配置
FRONTEND_PORT="${FRONTEND_PORT:-3100}"
BACKEND_PORT="${BACKEND_PORT:-6501}"

# Docker配置
COMPOSE_FILE="docker-compose.yml"

# 打印带颜色的消息
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

print_message "$BLUE" "========================================"
print_message "$BLUE" "   建辉慈善后台 - 一键部署脚本"
print_message "$BLUE" "========================================"
echo ""

# ============================================
# 使用说明
# ============================================

show_usage() {
    cat << EOF
用法: $0 [选项]

建辉慈善后台 - 前后端完整部署脚本

选项:
  -h, --help              显示此帮助信息
  -r, --rebuild          强制重新构建镜像
  -l, --logs             启动后查看日志
  -p, --frontend-port    指定前端端口（默认：3100）
  -b, --backend-port     指定后端端口（默认：6501）

环境变量:
  FRONTEND_PORT          前端服务端口（默认：3100）
  BACKEND_PORT           后端服务端口（默认：6501）

示例:
  # 基础部署（默认端口）
  $0

  # 指定前端端口
  $0 -p 8080

  # 指定后端端口
  $0 -b 8081

  # 同时指定前后端端口
  $0 -p 8080 -b 8081

  # 强制重新构建
  $0 --rebuild

  # 部署后查看日志
  $0 --logs

EOF
}

# 检查 Docker 是否安装
check_docker() {
    print_message "$YELLOW" "检查 Docker..."
    if ! command -v docker &> /dev/null; then
        print_message "$RED" "❌ Docker 未安装，请先安装 Docker"
        exit 1
    fi
    print_message "$GREEN" "✅ Docker 已安装: $(docker --version)"
}

# 检查 Docker Compose 是否安装
check_docker_compose() {
    print_message "$YELLOW" "检查 Docker Compose..."
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        print_message "$RED" "❌ Docker Compose 未安装，请先安装 Docker Compose"
        exit 1
    fi
    if command -v docker-compose &> /dev/null; then
        print_message "$GREEN" "✅ Docker Compose 已安装: $(docker-compose --version)"
    else
        print_message "$GREEN" "✅ Docker Compose 已安装: $(docker compose version)"
    fi
}

# 停止并删除旧容器
stop_old_containers() {
    print_message "$YELLOW" "停止旧容器..."
    cd "$(dirname "$0")"

    if command -v docker-compose &> /dev/null; then
        docker-compose down 2>/dev/null || true
    else
        docker compose down 2>/dev/null || true
    fi

    print_message "$GREEN" "✅ 旧容器已停止"
}

# 构建镜像
build_images() {
    print_message "$YELLOW" "开始构建 Docker 镜像..."
    cd "$(dirname "$0")"

    if command -v docker-compose &> /dev/null; then
        docker-compose build --no-cache
    else
        docker compose build --no-cache
    fi

    print_message "$GREEN" "✅ 镜像构建完成"
}

# 启动服务
start_services() {
    print_message "$YELLOW" "启动服务..."
    cd "$(dirname "$0")"

    # 创建临时的环境变量文件供 docker-compose 使用
    cat > .env << EOF
FRONTEND_PORT=${FRONTEND_PORT}
BACKEND_PORT=${BACKEND_PORT}
EOF

    # 设置容器的端口环境变量
    export FRONTEND_PORT
    export BACKEND_PORT

    if command -v docker-compose &> /dev/null; then
        FRONTEND_PORT=$FRONTEND_PORT BACKEND_PORT=$BACKEND_PORT docker-compose up -d
    else
        FRONTEND_PORT=$FRONTEND_PORT BACKEND_PORT=$BACKEND_PORT docker compose up -d
    fi

    # 清理临时环境变量文件
    rm -f .env

    print_message "$GREEN" "✅ 服务启动完成"
}

# 检查服务状态
check_services() {
    print_message "$YELLOW" "检查服务状态..."
    sleep 3

    echo ""
    print_message "$BLUE" "容器状态："
    docker ps --filter "name=moyi-admin" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

    echo ""
    print_message "$BLUE" "服务访问地址："
    print_message "$GREEN" "  🌐 前端访问地址: http://localhost:$FRONTEND_PORT"
    print_message "$GREEN" "  📡 后端 API 地址: http://localhost:$BACKEND_PORT"

    # 检查容器健康状态
    sleep 2
    if docker ps | grep -q "moyi-admin-frontend"; then
        print_message "$GREEN" "✅ 前端容器运行正常"
    else
        print_message "$RED" "❌ 前端容器启动失败"
        docker logs moyi-admin-frontend
    fi

    if docker ps | grep -q "moyi-admin"; then
        print_message "$GREEN" "✅ 后端容器运行正常"
    else
        print_message "$RED" "❌ 后端容器启动失败"
        docker logs moyi-admin
    fi
}

# 查看日志
view_logs() {
    echo ""
    print_message "$YELLOW" "查看最近日志（按 Ctrl+C 退出）："
    echo ""
    if command -v docker-compose &> /dev/null; then
        docker-compose logs -f --tail=50
    else
        docker compose logs -f --tail=50
    fi
}

# 主函数
main() {
    # 解析命令行参数
    REBUILD=false
    VIEW_LOGS=false
    SHOW_HELP=false

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
            -l|--logs)
                VIEW_LOGS=true
                shift
                ;;
            -p|--frontend-port)
                FRONTEND_PORT="$2"
                shift 2
                ;;
            -b|--backend-port)
                BACKEND_PORT="$2"
                shift 2
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

    # 显示配置信息
    print_message "$BLUE" "========================================"
    print_message "$BLUE" "   建辉慈善后台 - 一键部署脚本"
    print_message "$BLUE" "========================================"
    echo ""
    print_message "$BLUE" "部署配置："
    echo "  🌐 前端端口: $FRONTEND_PORT"
    echo "  📡 后端端口: $BACKEND_PORT"
    echo ""

    # 执行部署步骤
    check_docker
    check_docker_compose
    stop_old_containers

    if [ "$REBUILD" = true ]; then
        build_images
    else
        print_message "$YELLOW" "使用现有镜像（如需重新构建，请使用 --rebuild 参数）"
    fi

    start_services
    check_services

    if [ "$VIEW_LOGS" = true ]; then
        view_logs
    else
        echo ""
        print_message "$BLUE" "========================================"
        print_message "$GREEN" "🎉 部署完成！"
        print_message "$BLUE" "========================================"
        echo ""
        print_message "$BLUE" "访问地址："
        print_message "$GREEN" "  🌐 前端访问: http://localhost:$FRONTEND_PORT"
        print_message "$GREEN" "  📡 后端API: http://localhost:$BACKEND_PORT"
        print_message "$GREEN" "  🔧 管理后台: http://localhost:$FRONTEND_PORT/admin"
        echo ""
        print_message "$YELLOW" "常用命令："
        echo "  查看所有容器: docker ps"
        echo "  查看服务日志: docker-compose logs -f"
        echo "  停止所有服务: docker-compose down"
        echo "  重启服务: docker-compose restart"
        echo ""
    fi
}

# 执行主函数
main "$@"
