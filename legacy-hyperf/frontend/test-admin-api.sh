#!/bin/bash

# 后台管理系统 API 测试脚本
# 测试所有修复后的 API 端点

echo "========================================"
echo "后台管理系统 API 测试"
echo "========================================"
echo ""

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试函数
test_api() {
    local name=$1
    local url=$2
    local method=${3:-GET}
    local data=$4

    echo -n "测试 $name ... "

    if [ "$method" = "POST" ]; then
        response=$(curl -s -X POST -H "Content-Type: application/json" -d "$data" "$url" 2>&1)
    else
        response=$(curl -s "$url" 2>&1)
    fi

    http_code=$(curl -s -o /dev/null -w "%{http_code}" -X $method ${data:+-d "$data"} "${data:+-H "Content-Type: application/json"}" "$url")

    if [ "$http_code" = "200" ] || [ "$http_code" = "201" ]; then
        echo -e "${GREEN}✓ PASS${NC} (HTTP $http_code)"
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (HTTP $http_code)"
        echo "  Response: $response"
        return 1
    fi
}

echo "1. 测试公共 API 端点（通过 Vite 代理）"
echo "--------------------------------------"

test_api "统计数据 API" "http://localhost:3100/api/v1/stats/overview"
test_api "导航菜单 API" "http://localhost:3100/api/v1/navigation"
test_api "精选项目 API" "http://localhost:3100/api/v1/projects/featured"
test_api "轮播图 API" "http://localhost:3100/api/v1/slides"

echo ""
echo "2. 测试后台管理 API 端点"
echo "--------------------------------------"

test_api "后台统计 API" "http://localhost:3100/api/v1/admin/stats"
test_api "后台文章 API" "http://localhost:3100/api/v1/admin/articles"
test_api "后台项目 API" "http://localhost:3100/api/v1/admin/projects"
test_api "后台轮播图 API" "http://localhost:3100/api/v1/admin/slides"

echo ""
echo "3. 测试前端页面路由"
echo "--------------------------------------"

test_api "登录页面" "http://localhost:3100/admin/login"
test_api "后台首页" "http://localhost:6501/admin/login"

echo ""
echo "4. 测试认证 API"
echo "--------------------------------------"

# 测试登录 API（使用测试凭据）
test_api "登录 API" "http://localhost:3100/api/v1/admin/login" "POST" '{"username":"test","password":"test"}'

echo ""
echo "========================================"
echo "测试完成"
echo "========================================"
