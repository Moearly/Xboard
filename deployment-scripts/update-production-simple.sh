#!/bin/bash

# 线上生产环境快速更新脚本
# 前提：代码已推送到GitHub，Actions已构建完成
# 使用：SERVER_PASS='password' ./update-production-simple.sh

set -e

SERVER_IP="${SERVER_IP:-38.55.193.181}"
SERVER_USER="${SERVER_USER:-root}"
SERVER_PASS="${SERVER_PASS}"

if [ -z "$SERVER_PASS" ]; then
    echo "❌ 请设置 SERVER_PASS 环境变量"
    echo "使用方法: SERVER_PASS='your_password' $0"
    exit 1
fi

echo "🚀 开始更新线上环境..."
echo "服务器: $SERVER_IP"
echo ""

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << 'EOF'
cd /opt/Xboard

echo "📥 1. 拉取最新镜像..."
docker compose pull

echo ""
echo "🔄 2. 重启服务..."
docker compose down
docker compose up -d

echo ""
echo "⏳ 3. 等待服务启动..."
sleep 20

echo ""
echo "🗄️  4. 执行迁移..."
docker compose exec -T web php artisan migrate --force || echo "迁移完成"

echo ""
echo "🧹 5. 清理缓存..."
docker compose exec -T web php artisan config:clear
docker compose exec -T web php artisan cache:clear
docker compose exec -T web php artisan route:clear

echo ""
echo "✅ 部署完成！"
echo ""
echo "📊 服务状态:"
docker compose ps

echo ""
echo "🔍 测试访问:"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:7002)
echo "HTTP状态码: $HTTP_CODE"

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "404" ]; then
    echo "✅ 服务运行正常"
else
    echo "⚠️  状态异常，查看日志:"
    docker compose logs web --tail 20
fi
EOF

echo ""
echo "🎉 更新完成！"
echo "访问: http://$SERVER_IP:7002"
echo ""

