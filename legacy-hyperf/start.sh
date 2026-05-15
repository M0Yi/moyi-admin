#!/bin/bash

# ============================================
# 快速启动脚本（用于已有镜像）
# ============================================

set -e

# 颜色输出
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================
# 配置部分
# ============================================

# 端口配置
FRONTEND_PORT="${FRONTEND_PORT:-3100}"
BACKEND_PORT="${BACKEND_PORT:-6501}"

# ============================================
# 使用说明
# ============================================

show_usage() {
    cat << EOF
用法: $0 [选项]

快速启动服务（使用已有镜像）

选项:
  -h, --help              显示此帮助信息
  -p, --frontend-port    指定前端端口（默认：3100）
  -b, --backend-port     指定后端端口（默认：6501）

环境变量:
  FRONTEND_PORT          前端服务端口（默认：3100）
  BACKEND_PORT           后端服务端口（默认：6501）

示例:
  # 基础启动（默认端口）
  $0

  # 指定前端端口
  $0 -p 8080

  # 指定后端端口
  $0 -b 8081

  # 同时指定前后端端口
  $0 -p 8080 -b 8081

EOF
}

# ============================================
# 解析命令行参数
# ============================================

SHOW_HELP=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            SHOW_HELP=true
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
            echo -e "${RED}未知选项: $1${NC}"
            echo "使用 --help 查看帮助"
            exit 1
            ;;
    esac
done

if [ "$SHOW_HELP" = true ]; then
    show_usage
    exit 0
fi

# ============================================
# 启动服务
# ============================================

echo -e "${BLUE}========================================"
echo -e "${BLUE}   快速启动服务"
echo -e "${BLUE}========================================${NC}"
echo ""

echo -e "${BLUE}启动配置：${NC}"
echo -e "  🌐 前端端口: ${GREEN}$FRONTEND_PORT${NC}"
echo -e "  📡 后端端口: ${GREEN}$BACKEND_PORT${NC}"
echo ""

cd "$(dirname "$0")"

# 创建环境变量文件
cat > .env << EOF
FRONTEND_PORT=${FRONTEND_PORT}
BACKEND_PORT=${BACKEND_PORT}
EOF

# 启动服务
echo -e "${YELLOW}启动服务...${NC}"
if command -v docker-compose &> /dev/null; then
    FRONTEND_PORT=$FRONTEND_PORT BACKEND_PORT=$BACKEND_PORT docker-compose up -d
else
    FRONTEND_PORT=$FRONTEND_PORT BACKEND_PORT=$BACKEND_PORT docker compose up -d
fi

# 清理环境变量文件
rm -f .env

echo -e "${GREEN}✅ 服务启动完成${NC}"
echo ""

# 显示状态
echo -e "${BLUE}容器状态：${NC}"
sleep 2
docker ps --filter "name=moyi-admin" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"

echo ""
echo -e "${BLUE}服务访问地址：${NC}"
echo -e "${GREEN}  🌐 前端: ${GREEN}http://localhost:$FRONTEND_PORT${NC}"
echo -e "${GREEN}  📡 后端: ${GREEN}http://localhost:$BACKEND_PORT${NC}"
echo -e "${GREEN}  🔧 管理后台: ${GREEN}http://localhost:$FRONTEND_PORT/admin${NC}"
echo ""

echo -e "${YELLOW}常用命令：${NC}"
echo "  查看日志: docker-compose logs -f"
echo "  停止服务: docker-compose down"
echo "  重启服务: docker-compose restart"
echo ""
