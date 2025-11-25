#!/bin/bash

# XBoard Docker 测试脚本
# 用于快速启动、测试和验证多租户共享节点功能

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 函数：打印带颜色的消息
log_info() {
    echo -e "${BLUE}ℹ ${NC}$1"
}

log_success() {
    echo -e "${GREEN}✓${NC} $1"
}

log_error() {
    echo -e "${RED}✗${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# 函数：打印标题
print_header() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
}

# 函数：检查 Docker 是否运行
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        log_error "Docker 未运行，请先启动 Docker"
        exit 1
    fi
    log_success "Docker 正在运行"
}

# 函数：清理旧容器
cleanup() {
    print_header "清理旧容器和数据"
    
    log_info "停止并删除容器..."
    docker-compose -f docker-compose.test.yml down -v 2>/dev/null || true
    
    log_success "清理完成"
}

# 函数：启动服务
start_services() {
    print_header "启动测试环境"
    
    log_info "构建并启动容器..."
    docker-compose -f docker-compose.test.yml up -d --build
    
    log_info "等待服务启动..."
    sleep 10
    
    # 等待 MySQL 就绪
    log_info "等待 MySQL 就绪..."
    for i in {1..30}; do
        if docker-compose -f docker-compose.test.yml exec -T mysql mysqladmin ping -h localhost -u root -proot123456 --silent 2>/dev/null; then
            log_success "MySQL 已就绪"
            break
        fi
        if [ $i -eq 30 ]; then
            log_error "MySQL 启动超时"
            exit 1
        fi
        sleep 2
    done
    
    # 等待应用就绪
    log_info "等待应用就绪..."
    for i in {1..30}; do
        if curl -f http://localhost:8080/health > /dev/null 2>&1; then
            log_success "应用已就绪"
            break
        fi
        if [ $i -eq 30 ]; then
            log_error "应用启动超时"
            exit 1
        fi
        sleep 2
    done
    
    log_success "所有服务启动完成"
}

# 函数：运行测试
run_tests() {
    print_header "运行测试"
    
    log_info "1. 测试共享节点访问..."
    docker-compose -f docker-compose.test.yml exec -T xboard php test-shared-nodes.php
    
    log_info "2. 测试租户数据隔离..."
    docker-compose -f docker-compose.test.yml exec -T xboard php artisan test --filter MultiTenantSharedNodesTest || log_warning "PHPUnit 测试失败（可能未配置）"
    
    log_success "测试完成"
}

# 函数：API 测试
api_tests() {
    print_header "API 测试"
    
    BASE_URL="http://localhost:8080"
    
    # 测试1: 获取租户列表
    log_info "测试: 获取租户列表..."
    response=$(curl -s -w "\n%{http_code}" -H "Host: admin.xboard.test" "$BASE_URL/api/admin/tenants")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        log_success "租户列表 API 正常 (HTTP $http_code)"
    else
        log_error "租户列表 API 失败 (HTTP $http_code)"
    fi
    
    # 测试2: 获取共享节点
    log_info "测试: 获取共享节点..."
    response=$(curl -s -w "\n%{http_code}" -H "Host: admin.xboard.test" "$BASE_URL/api/admin/servers/all")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        log_success "共享节点 API 正常 (HTTP $http_code)"
        body=$(echo "$response" | sed '$d')
        node_count=$(echo "$body" | grep -o '"name"' | wc -l)
        log_info "   检测到 $node_count 个共享节点"
    else
        log_error "共享节点 API 失败 (HTTP $http_code)"
    fi
    
    # 测试3: 租户1访问节点
    log_info "测试: 租户1访问节点..."
    response=$(curl -s -w "\n%{http_code}" -H "Host: tenant1.xboard.test" "$BASE_URL/api/user/server/fetch")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ] || [ "$http_code" = "401" ]; then
        log_success "租户1节点访问 API 可达 (HTTP $http_code)"
    else
        log_error "租户1节点访问失败 (HTTP $http_code)"
    fi
    
    # 测试4: 租户识别
    log_info "测试: 租户域名识别..."
    for domain in "tenant1.xboard.test" "tenant2.xboard.test" "tenant3.xboard.test"; do
        response=$(curl -s -w "\n%{http_code}" -H "Host: $domain" "$BASE_URL/")
        http_code=$(echo "$response" | tail -n1)
        if [ "$http_code" = "200" ]; then
            log_success "   $domain 识别成功 (HTTP $http_code)"
        else
            log_warning "   $domain 识别异常 (HTTP $http_code)"
        fi
    done
}

# 函数：显示测试数据
show_test_data() {
    print_header "测试数据信息"
    
    log_info "租户信息:"
    docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "
        SELECT id, name, domain, status, admin_email 
        FROM tenants 
        ORDER BY id;" 2>/dev/null || log_warning "无法查询租户数据"
    
    log_info "共享节点信息:"
    docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "
        SELECT id, name, type, host, rate, \`show\` 
        FROM v2_server 
        WHERE \`show\` = 1 
        ORDER BY sort;" 2>/dev/null || log_warning "无法查询节点数据"
    
    log_info "用户统计:"
    docker-compose -f docker-compose.test.yml exec -T mysql mysql -u xboard -pxboard123 xboard -e "
        SELECT t.name as tenant_name, COUNT(u.id) as user_count 
        FROM tenants t 
        LEFT JOIN v2_user u ON t.id = u.tenant_id 
        GROUP BY t.id 
        ORDER BY t.id;" 2>/dev/null || log_warning "无法查询用户统计"
}

# 函数：显示访问信息
show_access_info() {
    print_header "访问信息"
    
    echo "🌐 Web 访问地址:"
    echo "   - 超级管理后台: http://localhost:8080"
    echo "     在浏览器中访问时，需要配置 hosts:"
    echo "     127.0.0.1 admin.xboard.test"
    echo ""
    echo "   - 租户1: http://localhost:8080"
    echo "     127.0.0.1 tenant1.xboard.test"
    echo ""
    echo "   - 租户2: http://localhost:8080"
    echo "     127.0.0.1 tenant2.xboard.test"
    echo ""
    echo "   - 租户3: http://localhost:8080"
    echo "     127.0.0.1 tenant3.xboard.test"
    echo ""
    echo "🔑 登录凭据:"
    echo "   - 租户1管理员: admin@tenant1.com / admin123"
    echo "   - 租户2管理员: admin@tenant2.com / admin123"
    echo "   - 租户3管理员: admin@tenant3.com / admin123"
    echo ""
    echo "📊 数据库访问:"
    echo "   - Host: localhost:3307"
    echo "   - Database: xboard"
    echo "   - User: xboard"
    echo "   - Password: xboard123"
    echo ""
    echo "💾 Redis 访问:"
    echo "   - Host: localhost:6380"
    echo ""
}

# 函数：查看日志
view_logs() {
    print_header "查看容器日志"
    
    log_info "最近的日志 (按 Ctrl+C 退出)..."
    docker-compose -f docker-compose.test.yml logs -f --tail=100
}

# 函数：进入容器
enter_container() {
    print_header "进入 XBoard 容器"
    
    docker-compose -f docker-compose.test.yml exec xboard sh
}

# 函数：停止服务
stop_services() {
    print_header "停止服务"
    
    docker-compose -f docker-compose.test.yml stop
    log_success "服务已停止"
}

# 函数：显示状态
show_status() {
    print_header "服务状态"
    
    docker-compose -f docker-compose.test.yml ps
}

# 函数：显示帮助
show_help() {
    echo ""
    echo "XBoard Docker 测试脚本"
    echo ""
    echo "用法: $0 [命令]"
    echo ""
    echo "命令:"
    echo "  start       - 启动测试环境（清理+构建+启动）"
    echo "  stop        - 停止服务"
    echo "  restart     - 重启服务"
    echo "  test        - 运行所有测试"
    echo "  api-test    - 运行 API 测试"
    echo "  data        - 显示测试数据"
    echo "  info        - 显示访问信息"
    echo "  logs        - 查看日志"
    echo "  shell       - 进入容器 Shell"
    echo "  status      - 显示服务状态"
    echo "  cleanup     - 清理所有容器和数据"
    echo "  help        - 显示帮助"
    echo ""
    echo "示例:"
    echo "  $0 start     # 启动完整测试环境"
    echo "  $0 test      # 运行测试"
    echo "  $0 logs      # 查看日志"
    echo ""
}

# 主逻辑
main() {
    case "${1:-start}" in
        start)
            check_docker
            cleanup
            start_services
            show_test_data
            api_tests
            show_access_info
            ;;
        stop)
            stop_services
            ;;
        restart)
            stop_services
            start_services
            ;;
        test)
            run_tests
            ;;
        api-test)
            api_tests
            ;;
        data)
            show_test_data
            ;;
        info)
            show_access_info
            ;;
        logs)
            view_logs
            ;;
        shell)
            enter_container
            ;;
        status)
            show_status
            ;;
        cleanup)
            cleanup
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "未知命令: $1"
            show_help
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"

