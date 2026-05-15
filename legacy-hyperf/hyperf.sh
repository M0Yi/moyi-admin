#!/bin/bash

# Hyperf 服务管理脚本
# 用法: ./hyperf.sh start|stop|restart|status

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 项目根目录
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Hyperf 配置
PHP_BIN="php"
HYPERF_CMD="bin/hyperf.php"
SERVER_CMD="server:watch"
SERVER_PORT=6501  # Hyperf 服务端口

# 查找 PHP 和 Composer 路径
find_php_path() {
    # 尝试多个可能的 PHP 路径
    for path in php /usr/local/bin/php /opt/homebrew/bin/php /usr/bin/php; do
        if command -v $path &> /dev/null; then
            echo $path
            return 0
        fi
    done
    echo "php"
    return 1
}

PHP_BIN=$(find_php_path)

# 获取 PHP 进程 PID
get_hyperf_pids() {
    # 查找 php bin/hyperf.php server:watch 进程
    pgrep -f "php.*hyperf" 2>/dev/null
}

# 显示消息
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 停止 Hyperf 服务
stop() {
    info "正在停止 Hyperf 服务..."
    
    local pids=$(get_hyperf_pids)
    
    if [ -z "$pids" ]; then
        warn "未找到运行中的 Hyperf 服务"
        return 0
    fi
    
    for pid in $pids; do
        if kill -0 $pid 2>/dev/null; then
            info "正在终止进程 PID: $pid"
            kill $pid 2>/dev/null
            
            # 等待进程结束
            local count=0
            while kill -0 $pid 2>/dev/null && [ $count -lt 30 ]; do
                sleep 1
                ((count++))
            done
            
            # 如果进程仍未结束，强制杀掉
            if kill -0 $pid 2>/dev/null; then
                warn "进程 $pid 仍未结束，强制终止..."
                kill -9 $pid 2>/dev/null
            fi
        fi
    done
    
    # 额外清理：杀掉可能残留的端口进程
    # Hyperf 默认端口 6501
    local port_pids=$(lsof -ti:6501 2>/dev/null)
    if [ -n "$port_pids" ]; then
        warn "清理占用 9501 端口的进程: $port_pids"
        echo $port_pids | xargs kill -9 2>/dev/null
    fi
    
    info "Hyperf 服务已停止"
}

# 启动 Hyperf 服务
start() {
    info "正在启动 Hyperf 服务..."
    
    cd "$PROJECT_DIR"
    
    # 检查 PHP 是否可用
    if ! command -v php &> /dev/null; then
        error "未找到 PHP，请确保已安装 PHP"
        exit 1
    fi
    
    # 检查 Hyperf 文件是否存在
    if [ ! -f "$PROJECT_DIR/$HYPERF_CMD" ]; then
        error "未找到 $HYPERF_CMD 文件"
        exit 1
    fi
    
    # 启动 Hyperf
    info "执行命令: $PHP_BIN $HYPERF_CMD $SERVER_CMD"
    $PHP_BIN bin/hyperf.php server:watch
}

# 重启 Hyperf 服务
restart() {
    info "正在重启 Hyperf 服务..."
    stop
    sleep 2
    start
}

# 查看服务状态
status() {
    local pids=$(get_hyperf_pids)
    
    if [ -n "$pids" ]; then
        info "Hyperf 服务运行中"
        echo "PID: $pids"
        
        # 显示进程详细信息
        ps -p $pids -o pid,ppid,cmd,etime 2>/dev/null || true
    else
        warn "Hyperf 服务未运行"
    fi
    
    # 检查端口占用
    if command -v lsof &> /dev/null; then
        local port_info=$(lsof -i:6501 2>/dev/null)
        if [ -n "$port_info" ]; then
            info "端口 6501 占用情况:"
            echo "$port_info"
        fi
    fi
}

# 主逻辑
case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    restart)
        restart
        ;;
    status)
        status
        ;;
    *)
        echo "用法: $0 {start|stop|restart|status}"
        echo ""
        echo "命令说明:"
        echo "  start   - 启动 Hyperf 服务 (php bin/hyperf.php server:watch)"
        echo "  stop    - 停止 Hyperf 服务"
        echo "  restart - 重启 Hyperf 服务"
        echo "  status  - 查看服务状态"
        exit 1
        ;;
esac

exit 0
