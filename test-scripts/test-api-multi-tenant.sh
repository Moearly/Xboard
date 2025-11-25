#!/bin/bash

# 多租户共享节点API测试脚本

echo "========================================"
echo "   多租户共享节点 API 测试"
echo "========================================"
echo ""

# 配置
ADMIN_DOMAIN="admin.vpnall.com"
ADMIN_API="http://${ADMIN_DOMAIN}/api/admin"

# 颜色输出
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 测试结果计数
PASS=0
FAIL=0

# 测试函数
test_api() {
    local name=$1
    local url=$2
    local method=${3:-GET}
    local data=${4:-}
    
    echo -n "测试: $name ... "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$url")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" -H "Content-Type: application/json" -d "$data" "$url")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ 通过${NC} (HTTP $http_code)"
        ((PASS++))
        return 0
    else
        echo -e "${RED}✗ 失败${NC} (HTTP $http_code)"
        echo "响应: $body"
        ((FAIL++))
        return 1
    fi
}

echo "【1】获取所有租户列表"
echo "----------------------------------------"
test_api "获取租户列表" "${ADMIN_API}/tenants"
echo ""

echo "【2】获取共享节点列表"
echo "----------------------------------------"
test_api "获取所有共享节点" "${ADMIN_API}/servers/all"
echo ""

echo "【3】获取租户可用节点（应该与全部节点相同）"
echo "----------------------------------------"
# 假设租户ID为1
test_api "获取租户1的可用节点" "${ADMIN_API}/tenants/1/servers"
test_api "获取租户2的可用节点" "${ADMIN_API}/tenants/2/servers"
echo ""

echo "【4】获取全局统计"
echo "----------------------------------------"
test_api "获取全局统计数据" "${ADMIN_API}/global/statistics"
echo ""

echo "【5】测试租户识别（通过域名）"
echo "----------------------------------------"
# 需要配置实际的租户域名
TENANT1_DOMAIN="tenant1.example.com"
TENANT2_DOMAIN="tenant2.example.com"

echo "测试租户1域名访问:"
curl -s -H "Host: ${TENANT1_DOMAIN}" "http://localhost/" | head -n 5
echo ""

echo "测试租户2域名访问:"
curl -s -H "Host: ${TENANT2_DOMAIN}" "http://localhost/" | head -n 5
echo ""

echo "========================================"
echo "   测试结果汇总"
echo "========================================"
echo -e "${GREEN}通过: $PASS${NC}"
echo -e "${RED}失败: $FAIL${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "${GREEN}所有测试通过！${NC}"
    exit 0
else
    echo -e "${RED}有测试失败！${NC}"
    exit 1
fi

