#!/bin/bash

# 检查 Xboard API 端点状态

SERVER="38.55.193.181:7001"

echo "🔍 检查 Xboard API 端点状态..."
echo "服务器: http://${SERVER}"
echo "================================"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 检查函数
check_endpoint() {
    local path=$1
    local method=${2:-GET}
    local data=$3
    
    echo -n "检查 ${method} ${path}: "
    
    if [ "$method" = "POST" ] && [ -n "$data" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" -X POST "http://${SERVER}${path}" \
            -H "Content-Type: application/json" \
            -d "$data" 2>/dev/null)
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" "http://${SERVER}${path}" 2>/dev/null)
    fi
    
    if [ "$response" = "200" ] || [ "$response" = "201" ]; then
        echo -e "${GREEN}✅ OK (${response})${NC}"
    elif [ "$response" = "422" ] || [ "$response" = "401" ] || [ "$response" = "403" ]; then
        echo -e "${YELLOW}⚠️  需要认证 (${response})${NC}"
    elif [ "$response" = "404" ]; then
        echo -e "${RED}❌ 未找到 (${response})${NC}"
    elif [ "$response" = "500" ] || [ "$response" = "502" ] || [ "$response" = "503" ]; then
        echo -e "${RED}❌ 服务器错误 (${response})${NC}"
    else
        echo -e "${YELLOW}⚠️  状态码: ${response}${NC}"
    fi
}

echo "1. 基础连接测试"
echo "----------------"
check_endpoint "/"
echo ""

echo "2. API 端点测试"
echo "----------------"
check_endpoint "/api/v2/guest/comm/config"
check_endpoint "/api/v2/passport/auth/login" "POST" '{"email":"admin@vpnall.com","password":"Admin2025"}'
check_endpoint "/api/v2/passport/auth/register" "POST" '{"email":"test@test.com"}'
echo ""

echo "3. 管理路径测试"
echo "----------------"
check_endpoint "/admin"
check_endpoint "/ea25d015"
check_endpoint "/816d41b5"
check_endpoint "/api/v2/ea25d015/stat/getOverride"
echo ""

echo "4. 静态资源测试"
echo "----------------"
check_endpoint "/assets/admin/index.html"
check_endpoint "/public/assets/admin/index.html"
echo ""

echo "5. 详细测试一个 API"
echo "--------------------"
echo "测试登录 API 详细响应:"
curl -X POST "http://${SERVER}/api/v2/passport/auth/login" \
    -H "Content-Type: application/json" \
    -d '{"email":"admin@vpnall.com","password":"Admin2025"}' \
    -s 2>/dev/null | head -c 200

echo ""
echo ""
echo "================================"
echo "📊 测试总结"
echo "================================"

# 获取服务器基本信息
echo ""
echo "服务器响应头:"
curl -I "http://${SERVER}" 2>/dev/null | head -5

echo ""
echo "💡 建议:"
echo "--------"
echo "如果大部分 API 返回 404，说明："
echo "1. Xboard 未正确部署或未运行"
echo "2. Nginx/Apache 配置错误"
echo "3. Laravel 路由未正确配置"
echo ""
echo "解决方案:"
echo "1. 运行部署脚本: ./deploy-xboard-remote.sh"
echo "2. SSH 登录服务器检查: ssh root@38.55.193.181"
echo "3. 查看服务器日志: tail -f /var/log/nginx/error.log"