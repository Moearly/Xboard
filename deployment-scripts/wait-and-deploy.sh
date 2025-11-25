#!/bin/bash

# 等待GitHub Actions构建完成并部署多租户版本
# 使用方法: ./wait-and-deploy.sh

set -e

# 服务器信息
SERVER_IP="38.55.193.181"
SERVER_USER="root"
SERVER_PASS='5z=x;7pu~fC~uUz'

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 多租户Docker镜像构建和部署脚本${NC}"
echo ""

# 检查GitHub Actions构建状态
echo -e "${YELLOW}📋 1. 检查GitHub Actions构建状态...${NC}"
echo "仓库: https://github.com/Moearly/Xboard"
echo "Actions: https://github.com/Moearly/Xboard/actions"
echo ""

# 等待用户确认构建完成
echo -e "${YELLOW}⏳ 请访问上述链接检查构建状态...${NC}"
echo "构建完成后，新的Docker镜像将发布到:"
echo "📦 ghcr.io/moearly/xboard:latest"
echo "📦 ghcr.io/moearly/xboard:new"
echo ""

read -p "构建是否已完成？(y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}❌ 请等待构建完成后再运行此脚本${NC}"
    exit 1
fi

echo -e "${GREEN}✅ 开始部署多租户版本...${NC}"
echo ""

# 连接服务器并部署
echo -e "${YELLOW}🔄 2. 连接服务器并更新部署...${NC}"

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP "
echo '🚀 开始多租户版本部署...'
cd /opt/Xboard

echo '⏹️  停止当前服务...'
docker stop \$(docker ps -q --filter name=xboard) || echo '没有运行的容器'
docker rm \$(docker ps -aq --filter name=xboard) || echo '没有容器需要删除'

echo '🗑️  清理旧镜像...'
docker rmi ghcr.io/cedar2025/xboard:new || echo '旧镜像已清理或不存在'

echo '📥 拉取新的多租户镜像...'
docker pull ghcr.io/moearly/xboard:latest
docker pull ghcr.io/moearly/xboard:new || echo '备用标签不存在，继续...'

echo '📝 创建新的compose文件...'
cat > docker-compose-multi-tenant.yml << 'EOF'
version: '3.8'

services:
  web:
    image: ghcr.io/moearly/xboard:latest
    container_name: xboard-multi-tenant-web
    restart: unless-stopped
    ports:
      - \"7002:7001\"
    volumes:
      - ./:/www
      - ./.docker/.data/redis:/data
    environment:
      - ENABLE_SQLITE=true
      - ENABLE_REDIS=true
    depends_on:
      - redis
    networks:
      - xboard

  horizon:
    image: ghcr.io/moearly/xboard:latest
    container_name: xboard-multi-tenant-horizon
    restart: unless-stopped
    volumes:
      - ./:/www
    command: php artisan horizon
    depends_on:
      - redis
    networks:
      - xboard

  redis:
    image: redis:7-alpine
    container_name: xboard-multi-tenant-redis
    restart: unless-stopped
    volumes:
      - ./.docker/.data/redis:/data
    networks:
      - xboard

networks:
  xboard:
    driver: bridge

volumes:
  redis_data:
EOF

echo '🚀 启动多租户服务...'
docker-compose -f docker-compose-multi-tenant.yml up -d

echo '⏳ 等待服务启动...'
sleep 15

echo '🗄️  运行数据库迁移...'
docker exec xboard-multi-tenant-web php artisan migrate --force

echo '🧹 清理缓存...'
docker exec xboard-multi-tenant-web php artisan config:clear
docker exec xboard-multi-tenant-web php artisan cache:clear
docker exec xboard-multi-tenant-web php artisan route:clear

echo '✅ 多租户版本部署完成！'
echo ''
echo '📋 部署信息:'
echo '镜像: ghcr.io/moearly/xboard:latest'
echo '容器: xboard-multi-tenant-web'
echo '端口: 7002'
echo '版本: 多租户版本'
echo ''
echo '🔍 验证部署:'
docker ps | grep xboard-multi-tenant
echo ''
echo '📊 检查多租户功能:'
docker exec xboard-multi-tenant-web ls -la app/Models/ | grep -i tenant || echo '检查模型文件...'
docker exec xboard-multi-tenant-web ls -la database/migrations/ | grep tenant || echo '检查迁移文件...'
"

echo ""
echo -e "${GREEN}🎉 多租户版本部署完成！${NC}"
echo ""
echo -e "${BLUE}📋 后续步骤:${NC}"
echo "1. 访问 http://$SERVER_IP:7002 验证网站"
echo "2. 运行 ./test-multi-tenant-api.sh http://$SERVER_IP:7002 测试API"
echo "3. 检查多租户功能是否正常"
echo ""
echo -e "${YELLOW}🔗 相关链接:${NC}"
echo "网站: http://$SERVER_IP:7002"
echo "管理后台: http://$SERVER_IP:7002/admin"
echo "GitHub仓库: https://github.com/Moearly/Xboard"
echo "Docker镜像: https://github.com/Moearly/Xboard/pkgs/container/xboard"
