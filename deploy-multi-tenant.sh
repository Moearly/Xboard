#!/bin/bash

# 多租户版本部署脚本
# 使用方法: ./deploy-multi-tenant.sh

set -e

echo "🚀 开始部署多租户版本..."

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 配置变量
PROJECT_DIR="/www/wwwroot/xboard"
BACKUP_DIR="/www/backup/xboard_$(date +%Y%m%d_%H%M%S)"

echo -e "${BLUE}📋 部署配置:${NC}"
echo "项目目录: $PROJECT_DIR"
echo "备份目录: $BACKUP_DIR"
echo ""

# 1. 数据备份
echo -e "${YELLOW}📦 1. 创建数据备份...${NC}"
mkdir -p $BACKUP_DIR

# 备份数据库
echo "备份数据库..."
mysqldump -u root -p xboard > $BACKUP_DIR/database_backup.sql

# 备份项目文件
echo "备份项目文件..."
cp -r $PROJECT_DIR $BACKUP_DIR/project_backup

echo -e "${GREEN}✅ 备份完成: $BACKUP_DIR${NC}"
echo ""

# 2. 停止服务
echo -e "${YELLOW}⏹️  2. 停止相关服务...${NC}"
systemctl stop nginx || echo "nginx未运行"
systemctl stop php8.1-fpm || systemctl stop php-fpm || echo "php-fpm未运行"
docker-compose down || echo "docker服务未运行"
echo ""

# 3. 更新代码
echo -e "${YELLOW}📥 3. 拉取最新代码...${NC}"
cd $PROJECT_DIR
git fetch origin
git reset --hard origin/master
git pull origin master

echo -e "${GREEN}✅ 代码更新完成${NC}"
echo ""

# 4. 安装依赖
echo -e "${YELLOW}📦 4. 更新依赖包...${NC}"
composer install --no-dev --optimize-autoloader
npm install --production
npm run build

echo ""

# 5. 数据库迁移
echo -e "${YELLOW}🗄️  5. 运行数据库迁移...${NC}"
echo -e "${RED}⚠️  注意：这将修改数据库结构，请确认已备份数据库！${NC}"
read -p "确认继续？(y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate --force
    echo -e "${GREEN}✅ 数据库迁移完成${NC}"
else
    echo -e "${RED}❌ 用户取消迁移，部署终止${NC}"
    exit 1
fi
echo ""

# 6. 清理缓存
echo -e "${YELLOW}🧹 6. 清理缓存...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize

echo ""

# 7. 设置权限
echo -e "${YELLOW}🔐 7. 设置文件权限...${NC}"
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 777 $PROJECT_DIR/storage
chmod -R 777 $PROJECT_DIR/bootstrap/cache

echo ""

# 8. 启动服务
echo -e "${YELLOW}🚀 8. 启动服务...${NC}"
systemctl start php8.1-fpm || systemctl start php-fpm
systemctl start nginx
docker-compose up -d || echo "跳过docker启动"

echo ""

# 9. 验证部署
echo -e "${YELLOW}🔍 9. 验证部署状态...${NC}"
sleep 5

# 检查服务状态
echo "检查服务状态..."
systemctl is-active nginx && echo -e "${GREEN}✅ Nginx运行正常${NC}" || echo -e "${RED}❌ Nginx异常${NC}"
systemctl is-active php8.1-fpm || systemctl is-active php-fpm && echo -e "${GREEN}✅ PHP-FPM运行正常${NC}" || echo -e "${RED}❌ PHP-FPM异常${NC}"

# 检查网站访问
echo "检查网站访问..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost | grep -q "200\|302"; then
    echo -e "${GREEN}✅ 网站访问正常${NC}"
else
    echo -e "${RED}❌ 网站访问异常${NC}"
fi

echo ""
echo -e "${GREEN}🎉 多租户版本部署完成！${NC}"
echo ""
echo -e "${BLUE}📋 部署信息:${NC}"
echo "备份位置: $BACKUP_DIR"
echo "项目版本: 多租户版本"
echo "部署时间: $(date)"
echo ""
echo -e "${YELLOW}📝 后续步骤:${NC}"
echo "1. 访问管理后台验证功能"
echo "2. 测试多租户API接口"
echo "3. 创建测试租户验证数据隔离"
echo "4. 监控系统运行状态"
echo ""
echo -e "${BLUE}🔗 相关链接:${NC}"
echo "管理后台: http://your-domain/admin"
echo "API文档: http://your-domain/api/documentation"
echo "项目仓库: https://github.com/Moearly/Xboard.git"
