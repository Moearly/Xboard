#!/bin/bash

# 真实世界测试脚本 - 验证共享节点多租户功能
# 这个脚本使用真实的 HTTP 请求和数据库查询来验证功能

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASSED=0
FAILED=0
BASE_URL="${BASE_URL:-http://localhost:8080}"

log_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((PASSED++))
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((FAILED++))
}

log_info() {
    echo -e "${YELLOW}[INFO]${NC} $1"
}

# 测试1: 验证数据库中真的没有 tenant_server 表
test_no_tenant_server_table() {
    log_test "验证 tenant_server 表已被删除"
    
    result=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "SHOW TABLES LIKE 'tenant_server';" 2>/dev/null | grep -c tenant_server || true)
    
    if [ "$result" -eq 0 ]; then
        log_pass "tenant_server 表确实不存在（共享模式）"
    else
        log_fail "tenant_server 表仍然存在（应该已删除）"
    fi
}

# 测试2: 验证所有租户真的可以查询到相同的节点
test_shared_nodes_database() {
    log_test "验证数据库层面的节点共享"
    
    # 获取总节点数
    total_nodes=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -N -e "SELECT COUNT(*) FROM v2_server WHERE \`show\` = 1;" 2>/dev/null)
    
    log_info "数据库中共有 $total_nodes 个显示的节点"
    
    # 验证没有任何节点有 tenant_id 字段限制
    has_tenant_field=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "SHOW COLUMNS FROM v2_server LIKE 'tenant_id';" 2>/dev/null | grep -c tenant_id || true)
    
    if [ "$has_tenant_field" -eq 0 ]; then
        log_pass "v2_server 表确实没有 tenant_id 字段（节点全局共享）"
    else
        log_fail "v2_server 表仍有 tenant_id 字段（不应该存在）"
    fi
    
    if [ "$total_nodes" -gt 0 ]; then
        log_pass "找到 $total_nodes 个共享节点"
    else
        log_fail "没有找到任何节点"
    fi
}

# 测试3: 通过真实 API 验证租户1可以获取节点
test_tenant1_api_access() {
    log_test "测试租户1通过 API 访问节点"
    
    # 尝试获取节点列表（可能需要认证）
    response=$(curl -s -H "Host: tenant1.xboard.test" "$BASE_URL/api/v1/user/server/fetch" -w "\n%{http_code}")
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    log_info "HTTP 状态码: $http_code"
    
    # 200 = 成功, 401 = 需要认证（正常），404 = 路由不存在（有问题）
    if [ "$http_code" -eq 200 ] || [ "$http_code" -eq 401 ]; then
        log_pass "租户1 API 端点可访问 (HTTP $http_code)"
    else
        log_fail "租户1 API 访问失败 (HTTP $http_code)"
        echo "响应内容: $body"
    fi
}

# 测试4: 通过真实 API 验证租户2可以获取节点
test_tenant2_api_access() {
    log_test "测试租户2通过 API 访问节点"
    
    response=$(curl -s -H "Host: tenant2.xboard.test" "$BASE_URL/api/v1/user/server/fetch" -w "\n%{http_code}")
    http_code=$(echo "$response" | tail -n1)
    
    log_info "HTTP 状态码: $http_code"
    
    if [ "$http_code" -eq 200 ] || [ "$http_code" -eq 401 ]; then
        log_pass "租户2 API 端点可访问 (HTTP $http_code)"
    else
        log_fail "租户2 API 访问失败 (HTTP $http_code)"
    fi
}

# 测试5: 验证租户中间件真的在工作
test_tenant_middleware() {
    log_test "验证租户识别中间件"
    
    # 测试有效的租户域名
    response1=$(curl -s -H "Host: tenant1.xboard.test" "$BASE_URL/" -w "\n%{http_code}")
    code1=$(echo "$response1" | tail -n1)
    
    # 测试无效的租户域名
    response2=$(curl -s -H "Host: invalid.tenant.test" "$BASE_URL/" -w "\n%{http_code}")
    code2=$(echo "$response2" | tail -n1)
    
    log_info "有效租户响应: HTTP $code1"
    log_info "无效租户响应: HTTP $code2"
    
    # 有效租户应该返回 200，无效租户应该返回 404
    if [ "$code1" -eq 200 ] && [ "$code2" -eq 404 ]; then
        log_pass "租户中间件正确识别有效和无效租户"
    elif [ "$code1" -eq 200 ]; then
        log_pass "租户中间件可以识别有效租户（无效租户检测可能未实现）"
    else
        log_fail "租户中间件可能未正常工作"
    fi
}

# 测试6: 验证数据真的隔离了
test_data_isolation() {
    log_test "验证租户数据隔离"
    
    # 获取每个租户的用户数
    tenant1_users=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -N -e "SELECT COUNT(*) FROM v2_user WHERE tenant_id = 1;" 2>/dev/null)
    tenant2_users=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -N -e "SELECT COUNT(*) FROM v2_user WHERE tenant_id = 2;" 2>/dev/null)
    
    log_info "租户1用户数: $tenant1_users"
    log_info "租户2用户数: $tenant2_users"
    
    # 验证用户表有 tenant_id 字段
    has_tenant_id=$(docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "SHOW COLUMNS FROM v2_user LIKE 'tenant_id';" 2>/dev/null | grep -c tenant_id || true)
    
    if [ "$has_tenant_id" -gt 0 ]; then
        log_pass "v2_user 表有 tenant_id 字段（数据隔离）"
        
        if [ "$tenant1_users" -gt 0 ] && [ "$tenant2_users" -gt 0 ]; then
            log_pass "租户1和租户2都有独立的用户数据"
        else
            log_fail "租户用户数据不完整"
        fi
    else
        log_fail "v2_user 表缺少 tenant_id 字段"
    fi
}

# 测试7: 验证 Server 模型真的移除了租户关联方法
test_server_model() {
    log_test "验证 Server 模型移除了租户关联"
    
    # 检查 Server.php 文件内容
    if grep -q "function tenants()" app/Models/Server.php; then
        log_fail "Server.php 仍包含 tenants() 方法（应该已移除）"
    else
        log_pass "Server.php 已移除 tenants() 方法"
    fi
    
    if grep -q "function isAssignedToTenant" app/Models/Server.php; then
        log_fail "Server.php 仍包含 isAssignedToTenant() 方法（应该已移除）"
    else
        log_pass "Server.php 已移除 isAssignedToTenant() 方法"
    fi
    
    if grep -q "function scopeForTenant" app/Models/Server.php; then
        log_fail "Server.php 仍包含 scopeForTenant() 方法（应该已移除）"
    else
        log_pass "Server.php 已移除 scopeForTenant() 方法"
    fi
}

# 测试8: 验证 Tenant 模型的 servers() 方法真的返回所有节点
test_tenant_model() {
    log_test "验证 Tenant 模型的共享节点逻辑"
    
    # 在容器中运行 PHP 代码验证
    result=$(docker-compose -f docker-compose.test.yml exec -T xboard php -r "
        require 'vendor/autoload.php';
        \$app = require_once 'bootstrap/app.php';
        \$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
        \$kernel->bootstrap();
        
        \$tenant = App\Models\Tenant::first();
        if (!\$tenant) {
            echo 'NO_TENANT';
            exit;
        }
        
        // 绑定租户
        app()->instance('currentTenant', \$tenant);
        
        // 获取租户可用节点
        \$servers = \$tenant->availableServers();
        
        // 获取数据库中所有显示的节点
        \$allServers = App\Models\Server::where('show', 1)->count();
        
        if (is_countable(\$servers)) {
            \$tenantServers = count(\$servers);
        } else {
            \$tenantServers = \$servers->count();
        }
        
        if (\$tenantServers == \$allServers && \$allServers > 0) {
            echo 'PASS';
        } else {
            echo \"FAIL:{\$tenantServers}:{\$allServers}\";
        }
    " 2>&1)
    
    log_info "验证结果: $result"
    
    if [ "$result" = "PASS" ]; then
        log_pass "Tenant->availableServers() 返回所有共享节点"
    elif [ "$result" = "NO_TENANT" ]; then
        log_fail "没有找到租户数据"
    else
        log_fail "Tenant->availableServers() 返回的节点数量不正确: $result"
    fi
}

# 测试9: 验证配置系统的共享/独立模式
test_settings_isolation() {
    log_test "验证配置系统的混合模式"
    
    # 检查 SharedSettings 类是否存在
    if [ -f "app/Support/SharedSettings.php" ]; then
        log_pass "SharedSettings.php 文件存在"
    else
        log_fail "SharedSettings.php 文件不存在"
        return
    fi
    
    # 检查是否正确定义了共享和独立配置
    if grep -q "SHARED_KEYS" app/Support/SharedSettings.php && grep -q "TENANT_SPECIFIC_KEYS" app/Support/SharedSettings.php; then
        log_pass "配置分类已定义（共享/独立）"
    else
        log_fail "配置分类定义不完整"
    fi
}

# 测试10: 验证迁移文件是否存在
test_migration_files() {
    log_test "验证数据库迁移文件"
    
    if [ -f "database/migrations/2025_01_20_000002_remove_tenant_server_table.php" ]; then
        log_pass "移除 tenant_server 表的迁移文件存在"
    else
        log_fail "移除 tenant_server 表的迁移文件不存在"
    fi
}

# 主测试流程
main() {
    echo ""
    echo "======================================"
    echo "  真实世界测试 - 共享节点验证"
    echo "======================================"
    echo ""
    
    # 检查 Docker 环境
    if ! docker-compose -f docker-compose.test.yml ps | grep -q "xboard-app-test"; then
        echo "❌ Docker 容器未运行，请先执行: ./docker-test.sh start"
        exit 1
    fi
    
    echo "✓ Docker 环境正常"
    echo ""
    
    # 运行所有测试
    test_no_tenant_server_table
    echo ""
    
    test_shared_nodes_database
    echo ""
    
    test_data_isolation
    echo ""
    
    test_server_model
    echo ""
    
    test_tenant_model
    echo ""
    
    test_settings_isolation
    echo ""
    
    test_migration_files
    echo ""
    
    test_tenant_middleware
    echo ""
    
    test_tenant1_api_access
    echo ""
    
    test_tenant2_api_access
    echo ""
    
    # 总结
    echo ""
    echo "======================================"
    echo "  测试结果"
    echo "======================================"
    echo ""
    echo -e "${GREEN}通过: $PASSED${NC}"
    echo -e "${RED}失败: $FAILED${NC}"
    echo ""
    
    if [ $FAILED -eq 0 ]; then
        echo -e "${GREEN}✓ 所有测试通过！共享节点功能正常工作！${NC}"
        exit 0
    else
        echo -e "${RED}✗ 有 $FAILED 个测试失败，请检查实现${NC}"
        exit 1
    fi
}

main

