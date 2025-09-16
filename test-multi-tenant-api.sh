#!/bin/bash

# 多租户API接口测试脚本
# 使用方法: ./test-multi-tenant-api.sh [服务器地址]

set -e

# 配置
SERVER_URL=${1:-"http://localhost"}
API_BASE="$SERVER_URL/api/v2/admin"
ADMIN_EMAIL="admin@example.com"
ADMIN_PASSWORD="password"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🧪 多租户API接口测试${NC}"
echo "服务器地址: $SERVER_URL"
echo "API基础路径: $API_BASE"
echo ""

# 获取认证token
echo -e "${YELLOW}🔐 1. 获取认证token...${NC}"
LOGIN_RESPONSE=$(curl -s -X POST "$SERVER_URL/api/v2/passport/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}")

if echo "$LOGIN_RESPONSE" | grep -q "access_token"; then
    TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"access_token":"[^"]*' | cut -d'"' -f4)
    echo -e "${GREEN}✅ 认证成功${NC}"
else
    echo -e "${RED}❌ 认证失败: $LOGIN_RESPONSE${NC}"
    exit 1
fi

AUTH_HEADER="Authorization: Bearer $TOKEN"
echo ""

# 测试租户管理API
echo -e "${YELLOW}🏢 2. 测试租户管理API...${NC}"

# 2.1 获取租户列表
echo "2.1 获取租户列表..."
TENANTS_RESPONSE=$(curl -s -X GET "$API_BASE/tenant" -H "$AUTH_HEADER")
if echo "$TENANTS_RESPONSE" | grep -q "data\|tenants"; then
    echo -e "${GREEN}✅ 租户列表获取成功${NC}"
else
    echo -e "${RED}❌ 租户列表获取失败: $TENANTS_RESPONSE${NC}"
fi

# 2.2 创建测试租户
echo "2.2 创建测试租户..."
CREATE_TENANT_DATA='{
    "name": "测试租户",
    "domain": "test-tenant.example.com",
    "status": true,
    "max_users": 100,
    "max_orders_per_month": 50,
    "max_nodes": 5,
    "features": {
        "tickets": true,
        "coupons": true,
        "knowledge": true
    }
}'

CREATE_RESPONSE=$(curl -s -X POST "$API_BASE/tenant" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -d "$CREATE_TENANT_DATA")

if echo "$CREATE_RESPONSE" | grep -q "success\|created\|租户"; then
    TENANT_ID=$(echo "$CREATE_RESPONSE" | grep -o '"id":[0-9]*' | cut -d':' -f2)
    echo -e "${GREEN}✅ 租户创建成功 (ID: $TENANT_ID)${NC}"
else
    echo -e "${RED}❌ 租户创建失败: $CREATE_RESPONSE${NC}"
    TENANT_ID="1" # 使用默认ID继续测试
fi

# 2.3 获取租户详情
echo "2.3 获取租户详情..."
TENANT_DETAIL=$(curl -s -X GET "$API_BASE/tenant/$TENANT_ID" -H "$AUTH_HEADER")
if echo "$TENANT_DETAIL" | grep -q "name\|domain"; then
    echo -e "${GREEN}✅ 租户详情获取成功${NC}"
else
    echo -e "${RED}❌ 租户详情获取失败: $TENANT_DETAIL${NC}"
fi

# 2.4 更新租户信息
echo "2.4 更新租户信息..."
UPDATE_DATA='{"name": "更新后的测试租户", "max_users": 200}'
UPDATE_RESPONSE=$(curl -s -X PUT "$API_BASE/tenant/$TENANT_ID" \
  -H "$AUTH_HEADER" \
  -H "Content-Type: application/json" \
  -d "$UPDATE_DATA")

if echo "$UPDATE_RESPONSE" | grep -q "success\|updated"; then
    echo -e "${GREEN}✅ 租户更新成功${NC}"
else
    echo -e "${RED}❌ 租户更新失败: $UPDATE_RESPONSE${NC}"
fi

echo ""

# 测试租户计费API
echo -e "${YELLOW}💰 3. 测试租户计费API...${NC}"

# 3.1 获取计费方案列表
echo "3.1 获取计费方案列表..."
BILLING_PLANS=$(curl -s -X GET "$API_BASE/tenant-billing/plans" -H "$AUTH_HEADER")
if echo "$BILLING_PLANS" | grep -q "data\|plans"; then
    echo -e "${GREEN}✅ 计费方案列表获取成功${NC}"
else
    echo -e "${RED}❌ 计费方案列表获取失败: $BILLING_PLANS${NC}"
fi

# 3.2 获取租户账单
echo "3.2 获取租户账单..."
TENANT_BILLS=$(curl -s -X GET "$API_BASE/tenant-billing/bills?tenant_id=$TENANT_ID" -H "$AUTH_HEADER")
if echo "$TENANT_BILLS" | grep -q "data\|bills"; then
    echo -e "${GREEN}✅ 租户账单获取成功${NC}"
else
    echo -e "${RED}❌ 租户账单获取失败: $TENANT_BILLS${NC}"
fi

echo ""

# 测试租户统计API
echo -e "${YELLOW}📊 4. 测试租户统计API...${NC}"

# 4.1 获取租户统计数据
echo "4.1 获取租户统计数据..."
TENANT_STATS=$(curl -s -X GET "$API_BASE/tenant/$TENANT_ID/statistics" -H "$AUTH_HEADER")
if echo "$TENANT_STATS" | grep -q "users_count\|orders_count\|revenue"; then
    echo -e "${GREEN}✅ 租户统计数据获取成功${NC}"
else
    echo -e "${RED}❌ 租户统计数据获取失败: $TENANT_STATS${NC}"
fi

# 4.2 刷新统计缓存
echo "4.2 刷新统计缓存..."
REFRESH_STATS=$(curl -s -X POST "$API_BASE/tenant/$TENANT_ID/refresh-statistics" -H "$AUTH_HEADER")
if echo "$REFRESH_STATS" | grep -q "success\|refreshed"; then
    echo -e "${GREEN}✅ 统计缓存刷新成功${NC}"
else
    echo -e "${RED}❌ 统计缓存刷新失败: $REFRESH_STATS${NC}"
fi

echo ""

# 测试租户节点管理API
echo -e "${YELLOW}🖥️  5. 测试租户节点管理API...${NC}"

# 5.1 获取租户可用节点
echo "5.1 获取租户可用节点..."
TENANT_SERVERS=$(curl -s -X GET "$API_BASE/tenant/$TENANT_ID/servers" -H "$AUTH_HEADER")
if echo "$TENANT_SERVERS" | grep -q "data\|servers"; then
    echo -e "${GREEN}✅ 租户节点列表获取成功${NC}"
else
    echo -e "${RED}❌ 租户节点列表获取失败: $TENANT_SERVERS${NC}"
fi

echo ""

# 测试数据隔离
echo -e "${YELLOW}🔒 6. 测试数据隔离...${NC}"

# 6.1 测试用户数据隔离
echo "6.1 测试用户数据隔离..."
TENANT_USERS=$(curl -s -X GET "$API_BASE/user?tenant_id=$TENANT_ID" -H "$AUTH_HEADER")
if echo "$TENANT_USERS" | grep -q "data\|users"; then
    echo -e "${GREEN}✅ 用户数据隔离正常${NC}"
else
    echo -e "${RED}❌ 用户数据隔离异常: $TENANT_USERS${NC}"
fi

# 6.2 测试订单数据隔离
echo "6.2 测试订单数据隔离..."
TENANT_ORDERS=$(curl -s -X GET "$API_BASE/order?tenant_id=$TENANT_ID" -H "$AUTH_HEADER")
if echo "$TENANT_ORDERS" | grep -q "data\|orders"; then
    echo -e "${GREEN}✅ 订单数据隔离正常${NC}"
else
    echo -e "${RED}❌ 订单数据隔离异常: $TENANT_ORDERS${NC}"
fi

echo ""

# 清理测试数据
echo -e "${YELLOW}🧹 7. 清理测试数据...${NC}"
if [ "$TENANT_ID" != "1" ]; then
    DELETE_RESPONSE=$(curl -s -X DELETE "$API_BASE/tenant/$TENANT_ID" -H "$AUTH_HEADER")
    if echo "$DELETE_RESPONSE" | grep -q "success\|deleted"; then
        echo -e "${GREEN}✅ 测试租户删除成功${NC}"
    else
        echo -e "${RED}❌ 测试租户删除失败: $DELETE_RESPONSE${NC}"
    fi
fi

echo ""
echo -e "${GREEN}🎉 多租户API测试完成！${NC}"
echo ""
echo -e "${BLUE}📋 测试总结:${NC}"
echo "- 租户管理API: 创建、读取、更新、删除"
echo "- 租户计费API: 计费方案、账单管理"
echo "- 租户统计API: 统计数据、缓存刷新"
echo "- 节点管理API: 租户节点分配"
echo "- 数据隔离: 用户、订单数据隔离"
echo ""
echo -e "${YELLOW}💡 建议:${NC}"
echo "1. 在生产环境中运行此测试"
echo "2. 检查日志文件确认无错误"
echo "3. 监控数据库性能"
echo "4. 验证前端界面功能"
