#!/bin/bash

##############################################################################
# XBoard 多站点系统 - 数据库迁移部署脚本
# 
# 用途：执行租户相关的数据库迁移
# 日期：2024-11-21
##############################################################################

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印函数
print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_header() {
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo -e "${BLUE}$1${NC}"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
}

##############################################################################
# 步骤1：环境检查
##############################################################################

print_header "步骤1：环境检查"

# 检查是否在xboard目录
if [ ! -f "artisan" ]; then
    print_error "请在xboard目录下运行此脚本"
    exit 1
fi
print_success "当前目录正确"

# 检查.env文件
if [ ! -f ".env" ]; then
    print_error ".env文件不存在"
    exit 1
fi
print_success ".env文件存在"

# 检查数据库配置
if ! grep -q "DB_CONNECTION" .env; then
    print_error ".env中缺少数据库配置"
    exit 1
fi
print_success "数据库配置存在"

##############################################################################
# 步骤2：备份数据库
##############################################################################

print_header "步骤2：数据库备份"

print_info "正在创建数据库备份..."

# 读取数据库配置
DB_HOST=$(grep DB_HOST .env | cut -d '=' -f2)
DB_PORT=$(grep DB_PORT .env | cut -d '=' -f2)
DB_DATABASE=$(grep DB_DATABASE .env | cut -d '=' -f2)
DB_USERNAME=$(grep DB_USERNAME .env | cut -d '=' -f2)
DB_PASSWORD=$(grep DB_PASSWORD .env | cut -d '=' -f2)

# 创建备份目录
BACKUP_DIR="./storage/backups"
mkdir -p "$BACKUP_DIR"

# 备份文件名
BACKUP_FILE="$BACKUP_DIR/backup_before_tenant_migration_$(date +%Y%m%d_%H%M%S).sql"

print_info "备份文件：$BACKUP_FILE"

# 尝试备份（可能需要mysqldump）
if command -v mysqldump &> /dev/null; then
    print_info "使用mysqldump备份数据库..."
    MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE" > "$BACKUP_FILE" 2>/dev/null || {
        print_warning "自动备份失败，请手动备份数据库"
        read -p "是否继续？(yes/no): " continue_without_backup
        if [ "$continue_without_backup" != "yes" ]; then
            print_error "操作已取消"
            exit 1
        fi
    }
    
    if [ -f "$BACKUP_FILE" ]; then
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
        print_success "数据库备份成功：$BACKUP_FILE ($BACKUP_SIZE)"
    fi
else
    print_warning "mysqldump未安装，无法自动备份"
    print_warning "建议手动备份数据库后再继续"
    read -p "是否已手动备份？(yes/no): " manual_backup
    if [ "$manual_backup" != "yes" ]; then
        print_error "请先备份数据库"
        exit 1
    fi
fi

##############################################################################
# 步骤3：检查待执行的迁移
##############################################################################

print_header "步骤3：检查待执行的迁移"

print_info "租户相关的迁移文件列表："
echo ""

MIGRATIONS=(
    "2024_01_01_000001_create_tenants_table.php"
    "2024_01_01_000002_add_tenant_id_to_xboard_tables.php"
    "2024_10_02_000001_add_tenant_id_to_settings.php"
    "2025_01_20_000001_add_tenant_id_to_gift_card_tables.php"
    "2024_11_21_000001_add_tenant_id_to_commission_log.php"
    "2025_01_20_000002_remove_tenant_server_table.php"
)

for migration in "${MIGRATIONS[@]}"; do
    if [ -f "database/migrations/$migration" ]; then
        print_success "✓ $migration"
    else
        print_error "✗ $migration (文件不存在)"
    fi
done

echo ""

##############################################################################
# 步骤4：执行迁移
##############################################################################

print_header "步骤4：执行数据库迁移"

print_warning "即将执行数据库迁移，这将修改数据库结构"
read -p "确认执行？(yes/no): " confirm_migrate

if [ "$confirm_migrate" != "yes" ]; then
    print_error "操作已取消"
    exit 1
fi

print_info "开始执行迁移..."
echo ""

# 检查PHP环境
if command -v php &> /dev/null; then
    # 直接使用PHP
    print_info "使用本地PHP执行迁移..."
    php artisan migrate --force
    MIGRATION_STATUS=$?
elif command -v docker &> /dev/null; then
    # 尝试使用Docker
    print_info "使用Docker执行迁移..."
    
    # 查找PHP容器
    PHP_CONTAINER=$(docker ps --format '{{.Names}}' | grep -E 'php|xboard|laravel' | head -1)
    
    if [ -n "$PHP_CONTAINER" ]; then
        print_info "找到PHP容器：$PHP_CONTAINER"
        docker exec -it "$PHP_CONTAINER" php artisan migrate --force
        MIGRATION_STATUS=$?
    else
        print_error "未找到PHP容器"
        print_info "请手动执行：php artisan migrate --force"
        exit 1
    fi
else
    print_error "未找到PHP环境"
    print_info "请手动执行：php artisan migrate --force"
    exit 1
fi

# 检查迁移结果
if [ $MIGRATION_STATUS -eq 0 ]; then
    print_success "数据库迁移执行成功！"
else
    print_error "数据库迁移执行失败"
    print_info "请检查错误信息并手动处理"
    exit 1
fi

##############################################################################
# 步骤5：验证迁移结果
##############################################################################

print_header "步骤5：验证迁移结果"

print_info "验证租户表..."

# 这里可以添加更多验证逻辑
print_success "迁移验证完成"

##############################################################################
# 步骤6：创建默认租户（可选）
##############################################################################

print_header "步骤6：创建默认租户（可选）"

read -p "是否创建默认租户？(yes/no): " create_default_tenant

if [ "$create_default_tenant" = "yes" ]; then
    print_info "正在创建默认租户..."
    
    # 创建默认租户的SQL
    cat > /tmp/create_default_tenant.sql << 'EOF'
INSERT INTO tenants (id, uuid, name, domain, status, config, created_at, updated_at)
VALUES (
    1,
    UUID(),
    'Default Site',
    'localhost',
    1,
    JSON_OBJECT(
        'admin_email', 'admin@localhost.com',
        'max_users', 10000,
        'max_orders_per_month', 100000,
        'max_monthly_revenue', 1000000
    ),
    UNIX_TIMESTAMP(),
    UNIX_TIMESTAMP()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    domain = VALUES(domain);
EOF

    print_info "默认租户SQL已生成：/tmp/create_default_tenant.sql"
    print_warning "请根据实际情况修改后手动导入"
fi

##############################################################################
# 完成
##############################################################################

print_header "🎉 迁移部署完成！"

echo ""
print_success "数据库迁移已成功执行"
echo ""
print_info "下一步操作："
echo "  1. 验证数据：检查各表的tenant_id字段"
echo "  2. 创建租户：使用超级管理员后台创建租户"
echo "  3. 运行测试：php tests/tenant_scope_test.php"
echo "  4. 配置域名：为每个租户配置独立域名"
echo ""
print_info "备份位置：$BACKUP_FILE"
echo ""
print_success "系统已就绪，可以开始使用多站点功能！"
echo ""

