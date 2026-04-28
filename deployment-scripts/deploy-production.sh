#!/bin/bash

#############################################
# XBoard 线上生产环境部署脚本
# 功能：拉取镜像、自动备份、更新服务、迁移数据
# 使用：SERVER_PASS='password' ./deploy-production.sh
#############################################

set -e

# 服务器配置
SERVER_IP="${SERVER_IP:-38.55.193.181}"
SERVER_USER="${SERVER_USER:-root}"
SERVER_PASS="${SERVER_PASS}"
REMOTE_DIR="${REMOTE_DIR:-/opt/Xboard}"

# 检查密码
if [ -z "$SERVER_PASS" ]; then
    echo "❌ 请设置 SERVER_PASS 环境变量"
    echo "使用方法: SERVER_PASS='your_password' $0"
    exit 1
fi

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo ""
echo -e "${CYAN}╔════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     XBoard 线上生产环境部署脚本            ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}服务器: $SERVER_IP${NC}"
echo -e "${BLUE}目录: $REMOTE_DIR${NC}"
echo -e "${BLUE}时间: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
echo ""

# 询问是否继续
read -p "$(echo -e ${YELLOW}确认开始部署？${NC} [y/N]: )" -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "取消部署"
    exit 0
fi
echo ""

BACKUP_TAG=$(date +%Y%m%d_%H%M%S)

echo -e "${CYAN}开始部署流程...${NC}"
echo ""

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP bash << ENDSSH
set -e

cd $REMOTE_DIR

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 1/6: 备份当前数据${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# 创建备份目录
mkdir -p backups

echo "📦 1.1 备份容器镜像..."
docker commit xboard-web-1 xboard-backup:$BACKUP_TAG 2>/dev/null && \
    echo "✅ 容器备份: xboard-backup:$BACKUP_TAG" || \
    echo "⚠️  容器备份跳过（容器可能不存在）"

echo ""
echo "💾 1.2 备份数据库..."
# 尝试多种备份方式
if docker compose exec -T web php artisan backup:database > backups/db_$BACKUP_TAG.sql 2>/dev/null; then
    DB_SIZE=\$(du -h backups/db_$BACKUP_TAG.sql | cut -f1)
    echo "✅ 数据库备份: backups/db_$BACKUP_TAG.sql (\$DB_SIZE)"
elif [ -f storage/database.sqlite ]; then
    cp storage/database.sqlite backups/database_$BACKUP_TAG.sqlite
    DB_SIZE=\$(du -h backups/database_$BACKUP_TAG.sqlite | cut -f1)
    echo "✅ SQLite备份: backups/database_$BACKUP_TAG.sqlite (\$DB_SIZE)"
else
    echo "⚠️  数据库备份跳过（未找到数据库文件）"
fi

echo ""
echo "📁 1.3 备份配置文件..."
[ -f .env ] && cp .env backups/.env_$BACKUP_TAG && echo "✅ 配置备份: backups/.env_$BACKUP_TAG"

echo ""
echo -e "\${GREEN}✅ 备份完成${NC}"
echo -e "\${BLUE}备份标签: $BACKUP_TAG${NC}"
echo ""

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 2/6: 拉取最新镜像${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

docker compose pull || {
    echo -e "\${RED}❌ 镜像拉取失败${NC}"
    exit 1
}
echo -e "\${GREEN}✅ 镜像拉取成功${NC}"
echo ""

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 3/6: 停止旧服务${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

docker compose down
echo -e "\${GREEN}✅ 旧服务已停止${NC}"
echo ""

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 4/6: 启动新服务${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

docker compose up -d
echo ""
echo "⏳ 等待服务启动..."
sleep 20

# 检查服务状态
if docker compose ps | grep -q "Up"; then
    echo -e "\${GREEN}✅ 服务启动成功${NC}"
else
    echo -e "\${RED}❌ 服务启动失败${NC}"
    echo "查看日志："
    docker compose logs web --tail 20
    exit 1
fi
echo ""

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 5/6: 数据库迁移和缓存清理${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo "🗄️  5.1 执行数据库迁移..."
docker compose exec -T web php artisan migrate --force 2>&1 | grep -v "^$" || echo "迁移完成"

echo ""
echo "🧹 5.2 清理缓存..."
docker compose exec -T web php artisan config:clear
docker compose exec -T web php artisan cache:clear
docker compose exec -T web php artisan route:clear
docker compose exec -T web php artisan view:clear

echo ""
echo "🔧 5.3 优化性能..."
docker compose exec -T web php artisan config:cache 2>/dev/null || echo "配置缓存跳过"
docker compose exec -T web php artisan route:cache 2>/dev/null || echo "路由缓存跳过"

echo -e "\${GREEN}✅ 迁移和优化完成${NC}"
echo ""

echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${YELLOW}  步骤 6/6: 验证部署结果${NC}"
echo -e "\${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo "📊 6.1 容器状态："
docker compose ps

echo ""
echo "🔍 6.2 测试访问："
HTTP_CODE=\$(curl -s -o /dev/null -w "%{http_code}" http://localhost:7002 2>/dev/null)
echo "HTTP状态码: \$HTTP_CODE"

if [ "\$HTTP_CODE" = "200" ] || [ "\$HTTP_CODE" = "404" ]; then
    echo -e "\${GREEN}✅ 服务运行正常${NC}"
    SERVICE_STATUS="正常"
else
    echo -e "\${YELLOW}⚠️  HTTP状态码异常${NC}"
    SERVICE_STATUS="异常"
fi

echo ""
echo "📝 6.3 查看最新日志："
docker compose logs web --tail 5

echo ""
echo -e "\${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "\${GREEN}  ✅ 部署完成！${NC}"
echo -e "\${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# 记录部署日志
cat > backups/deploy_$BACKUP_TAG.log << DEPLOYLOG
部署时间: \$(date '+%Y-%m-%d %H:%M:%S')
备份标签: $BACKUP_TAG
服务状态: \$SERVICE_STATUS
HTTP状态: \$HTTP_CODE
镜像版本: ghcr.io/moearly/xboard:latest
DEPLOYLOG

echo ""
echo "📋 部署信息已保存到: backups/deploy_$BACKUP_TAG.log"

ENDSSH

# 返回本地后显示总结
echo ""
echo -e "${CYAN}╔════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║           部署总结                         ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}🌐 访问地址:${NC}"
echo "   网站: http://$SERVER_IP:7002"
echo "   管理: http://$SERVER_IP:7002/admin"
echo ""
echo -e "${BLUE}💾 备份信息:${NC}"
echo "   标签: xboard-backup:$BACKUP_TAG"
echo "   位置: $REMOTE_DIR/backups/"
echo ""
echo -e "${BLUE}📝 常用命令:${NC}"
echo "   查看日志: ssh $SERVER_USER@$SERVER_IP 'cd $REMOTE_DIR && docker compose logs -f web'"
echo "   重启服务: ssh $SERVER_USER@$SERVER_IP 'cd $REMOTE_DIR && docker compose restart'"
echo "   查看备份: ssh $SERVER_USER@$SERVER_IP 'ls -lh $REMOTE_DIR/backups/'"
echo ""
echo -e "${YELLOW}💡 回滚方法（如果出现问题）:${NC}"
echo "   ssh $SERVER_USER@$SERVER_IP << 'ROLLBACK'"
echo "     cd $REMOTE_DIR"
echo "     docker compose down"
echo "     docker tag xboard-backup:$BACKUP_TAG ghcr.io/moearly/xboard:latest"
echo "     docker compose up -d"
echo "   ROLLBACK"
echo ""
echo -e "${GREEN}🎉 所有操作完成！${NC}"
echo ""

