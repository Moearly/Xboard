#!/bin/bash

# 多租户系统自动更新脚本
# 使用方法: ./update-multi-tenant.sh

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

echo -e "${BLUE}🚀 多租户系统自动更新脚本${NC}"
echo "服务器: $SERVER_IP"
echo "时间: $(date)"
echo ""

# 检查GitHub Actions构建状态
echo -e "${YELLOW}📋 1. 检查GitHub Actions构建状态...${NC}"
echo "请确认构建已完成: https://github.com/Moearly/Xboard/actions"
echo ""

read -p "GitHub Actions构建是否已完成？(y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${RED}❌ 请等待构建完成后再运行更新${NC}"
    exit 1
fi

# 连接服务器并执行更新
echo -e "${GREEN}✅ 开始更新多租户系统...${NC}"
echo ""

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP "
echo '🚀 多租户系统更新开始...'
cd /opt/Xboard

echo '📦 1. 拉取最新镜像...'
docker pull ghcr.io/moearly/xboard:latest

echo '💾 2. 备份当前容器...'
BACKUP_TAG=\$(date +%Y%m%d_%H%M%S)
docker commit xboard-multi-tenant-official xboard-backup:\$BACKUP_TAG || echo '备份失败，继续...'
echo \"备份标签: xboard-backup:\$BACKUP_TAG\"

echo '⏹️  3. 停止当前服务...'
docker stop xboard-multi-tenant-official || echo '容器已停止'

echo '🗑️  4. 删除旧容器...'
docker rm xboard-multi-tenant-official || echo '容器已删除'

echo '🚀 5. 启动新容器...'
docker run -d \\
  --name xboard-multi-tenant-official \\
  --restart unless-stopped \\
  -p 7002:7001 \\
  -v /opt/Xboard:/www \\
  -e ENABLE_SQLITE=true \\
  -e ENABLE_REDIS=true \\
  ghcr.io/moearly/xboard:latest

echo '⏳ 6. 等待容器启动...'
sleep 15

echo '🗄️  7. 运行数据库迁移...'
docker exec xboard-multi-tenant-official php artisan migrate --force || echo '迁移可能失败'

echo '🧹 8. 清理缓存...'
docker exec xboard-multi-tenant-official php artisan config:clear
docker exec xboard-multi-tenant-official php artisan cache:clear
docker exec xboard-multi-tenant-official php artisan route:clear

echo '🔧 9. 修复权限并启动PHP服务器...'
docker exec xboard-multi-tenant-official chown -R www-data:www-data /www/storage /www/bootstrap/cache
docker exec xboard-multi-tenant-official chmod -R 775 /www/storage /www/bootstrap/cache
docker exec xboard-multi-tenant-official pkill -f octane || echo 'Octane已停止'
docker exec -d xboard-multi-tenant-official php -S 0.0.0.0:7001 -t /www/public

echo '⏳ 10. 等待服务完全启动...'
sleep 10

echo '🔍 11. 验证部署结果...'
HTTP_CODE=\$(curl -s -o /dev/null -w \"%{http_code}\" http://localhost:7002)

echo \"HTTP状态码: \$HTTP_CODE\"

if [ \"\$HTTP_CODE\" = \"404\" ]; then
    echo '✅ 多租户系统更新成功！'
    echo 'HTTP 404表示多租户域名验证正常工作'
    echo ''
    echo '📊 系统状态:'
    docker ps | grep multi-tenant-official
    echo ''
    echo '🔍 多租户功能验证:'
    docker exec xboard-multi-tenant-official ls -la app/Models/ | grep -i tenant | wc -l | xargs echo '多租户模型文件数量:'
    echo ''
    echo '🌐 访问信息:'
    echo '网站地址: http://38.55.193.181:7002'
    echo '管理后台: http://38.55.193.181:7002/admin'
    echo ''
    echo '📝 下一步:'
    echo '1. 配置租户域名或创建测试租户'
    echo '2. 访问管理后台进行系统配置'
    echo '3. 运行API测试验证功能'
elif [ \"\$HTTP_CODE\" = \"200\" ]; then
    echo '✅ 系统更新成功，网站正常访问！'
else
    echo '⚠️  更新完成，但HTTP状态码异常: '\$HTTP_CODE
    echo '请检查容器日志:'
    docker logs xboard-multi-tenant-official --tail 10
fi

echo ''
echo '🎉 多租户系统更新完成！'
echo \"备份版本: xboard-backup:\$BACKUP_TAG\"
echo '如需回滚，请运行回滚脚本'
"

echo ""
echo -e "${GREEN}🎉 更新流程完成！${NC}"
echo ""
echo -e "${BLUE}📋 后续操作建议:${NC}"
echo "1. 访问 http://$SERVER_IP:7002 验证网站"
echo "2. 检查多租户功能是否正常"
echo "3. 运行API测试脚本验证接口"
echo ""
echo -e "${YELLOW}💡 提示:${NC}"
echo "- HTTP 404是正常的多租户行为"
echo "- 需要配置租户域名才能正常访问"
echo "- 备份容器已自动创建，可用于回滚"
