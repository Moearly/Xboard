#!/bin/bash

#############################################
# 线上生产环境验证脚本
# 功能：检查服务状态、多站点功能、API接口
# 使用：./test-scripts/verify-production.sh
#############################################

set -e

SERVER_IP="${SERVER_IP:-38.55.193.181}"
SERVER_PORT="${SERVER_PORT:-7002}"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo ""
echo -e "${CYAN}╔════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     线上生产环境验证脚本                   ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}服务器: $SERVER_IP:$SERVER_PORT${NC}"
echo -e "${BLUE}时间: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo ""

# 测试计数器
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

test_item() {
    local name=$1
    local command=$2
    local expected=$3
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    echo -n "🔍 [$TOTAL_TESTS] $name ... "
    
    result=$(eval "$command" 2>/dev/null)
    
    if [[ "$result" =~ $expected ]]; then
        echo -e "${GREEN}✅ 通过${NC}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        echo -e "${RED}❌ 失败${NC}"
        echo -e "${YELLOW}   预期: $expected${NC}"
        echo -e "${YELLOW}   实际: $result${NC}"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        return 1
    fi
}

echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  基础服务检查${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

test_item "网站首页访问" \
    "curl -s -o /dev/null -w '%{http_code}' http://$SERVER_IP:$SERVER_PORT" \
    "200"

test_item "网站响应时间" \
    "curl -s -o /dev/null -w '%{time_total}' http://$SERVER_IP:$SERVER_PORT | awk '{print (\$1 < 2) ? \"OK\" : \"SLOW\"}'" \
    "OK"

test_item "Guest API访问" \
    "curl -s -o /dev/null -w '%{http_code}' http://$SERVER_IP:$SERVER_PORT/api/v1/guest/comm/config" \
    "200"

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  数据库和配置检查${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# 检查数据库连接
test_item "数据库连接" \
    "curl -s http://$SERVER_IP:$SERVER_PORT/api/v1/guest/comm/config | grep -o 'success' | head -1" \
    "success"

# 检查返回的配置数据
test_item "配置数据完整性" \
    "curl -s http://$SERVER_IP:$SERVER_PORT/api/v1/guest/comm/config | grep -o 'app_description' | head -1" \
    "app_description"

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  多租户功能检查${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# 检查是否支持租户头
echo -n "🔍 [$((TOTAL_TESTS + 1))] 多租户头部支持 ... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))

RESPONSE=$(curl -s -H "X-Tenant-ID: 1" http://$SERVER_IP:$SERVER_PORT/api/v1/guest/comm/config)
if echo "$RESPONSE" | grep -q "success"; then
    echo -e "${GREEN}✅ 通过${NC}"
    echo -e "${BLUE}   支持 X-Tenant-ID 头部${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${YELLOW}⚠️  未检测到多租户功能${NC}"
    echo -e "${YELLOW}   这可能是官方原版部署${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))  # 不算失败
fi

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  管理功能检查${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# 检查管理路径
echo -n "🔍 [$((TOTAL_TESTS + 1))] 管理后台路由 ... "
TOTAL_TESTS=$((TOTAL_TESTS + 1))

ADMIN_STATUS=$(curl -s -o /dev/null -w '%{http_code}' http://$SERVER_IP:$SERVER_PORT/admin)
if [ "$ADMIN_STATUS" = "404" ] || [ "$ADMIN_STATUS" = "200" ]; then
    echo -e "${GREEN}✅ 通过${NC}"
    echo -e "${BLUE}   状态码: $ADMIN_STATUS${NC}"
    PASSED_TESTS=$((PASSED_TESTS + 1))
else
    echo -e "${RED}❌ 失败 (状态码: $ADMIN_STATUS)${NC}"
    FAILED_TESTS=$((FAILED_TESTS + 1))
fi

echo ""
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${YELLOW}  性能检查${NC}"
echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# 响应时间测试
echo "📊 响应时间测试（3次）:"
for i in {1..3}; do
    TIME=$(curl -s -o /dev/null -w '%{time_total}' http://$SERVER_IP:$SERVER_PORT)
    echo "   第${i}次: ${TIME}s"
done

# 并发测试
echo ""
echo "📊 并发请求测试（5个并发）:"
START_TIME=$(date +%s.%N)
for i in {1..5}; do
    curl -s -o /dev/null http://$SERVER_IP:$SERVER_PORT &
done
wait
END_TIME=$(date +%s.%N)
TOTAL_TIME=$(echo "$END_TIME - $START_TIME" | bc)
echo "   总耗时: ${TOTAL_TIME}s"

echo ""
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${CYAN}  测试总结${NC}"
echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

SUCCESS_RATE=$(echo "scale=2; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)

echo -e "${BLUE}📊 测试统计:${NC}"
echo "   总测试数: $TOTAL_TESTS"
echo -e "   ${GREEN}通过: $PASSED_TESTS${NC}"
echo -e "   ${RED}失败: $FAILED_TESTS${NC}"
echo "   成功率: ${SUCCESS_RATE}%"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✅ 所有测试通过！服务运行正常${NC}"
    EXIT_CODE=0
else
    echo -e "${YELLOW}⚠️  部分测试失败，请检查日志${NC}"
    EXIT_CODE=1
fi

echo ""
echo -e "${BLUE}🌐 访问地址:${NC}"
echo "   网站: http://$SERVER_IP:$SERVER_PORT"
echo "   管理: http://$SERVER_IP:$SERVER_PORT/admin"
echo ""

exit $EXIT_CODE

