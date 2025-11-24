#!/bin/bash

#############################################
# Xboard 官方标准部署脚本
# 基于官方 Docker Compose 部署方式
# 测试通过 - 2025年9月15日
#############################################

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

echo -e "${BLUE}╔══════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Xboard 官方标准部署脚本                ║${NC}"
echo -e "${BLUE}║       基于官方 Docker Compose               ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════╝${NC}"
echo ""

# 获取配置
read -p "请输入部署端口 (默认: 7002): " PORT
PORT=${PORT:-7002}

read -p "请输入管理员邮箱 (默认: admin@vpnall.com): " ADMIN_EMAIL
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@vpnall.com}

echo ""
echo -e "${BLUE}部署配置:${NC}"
echo "  服务器IP: $SERVER_IP"
echo "  部署端口: $PORT"
echo "  管理员邮箱: $ADMIN_EMAIL"
echo ""

read -p "确认开始部署? (y/n): " confirm
if [ "$confirm" != "y" ]; then
    exit 1
fi

# 检查 sshpass
if ! command -v sshpass &> /dev/null; then
    echo -e "${YELLOW}安装 sshpass...${NC}"
    if [[ "$OSTYPE" == "darwin"* ]]; then
        brew install hudochenkov/sshpass/sshpass 2>/dev/null || {
            echo -e "${RED}请先手动安装 sshpass:${NC}"
            echo "brew install hudochenkov/sshpass/sshpass"
            exit 1
        }
    fi
fi

echo -e "${BLUE}[1/4] 连接服务器并清理环境...${NC}"

# 清理旧环境
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
# 停止旧容器
cd /opt && find . -name "docker-compose.yml" -exec dirname {} \; | xargs -I {} sh -c 'cd {} && docker compose down 2>/dev/null || true'

# 清理旧文件
rm -rf /opt/Xboard /opt/xboard /opt/vpnall

# 清理 Docker 资源
docker system prune -f

echo "环境清理完成"
EOF

echo -e "${BLUE}[2/4] 下载并配置 Xboard...${NC}"

# 按照官方文档部署
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
cd /opt

# 按照官方文档克隆
git clone -b compose --depth 1 https://github.com/cedar2025/Xboard

cd Xboard

# 修改端口映射
sed -i 's/7001:7001/${PORT}:7001/g' compose.yaml

echo "Xboard 下载和配置完成"
EOF

echo -e "${BLUE}[3/4] 运行官方安装程序...${NC}"

# 运行官方安装
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
cd /opt/Xboard

# 按照官方文档运行安装
docker compose run --rm -e ENABLE_SQLITE=true -e ENABLE_REDIS=true -e ADMIN_ACCOUNT=${ADMIN_EMAIL} web php artisan xboard:install

echo "安装程序执行完成"
EOF

echo -e "${BLUE}[4/4] 启动服务并获取访问信息...${NC}"

# 启动服务
INSTALL_OUTPUT=$(sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
cd /opt/Xboard

# 启动所有服务
docker compose up -d

# 等待服务启动
sleep 10

# 获取管理路径
ADMIN_PATH=\$(docker compose exec web php artisan tinker --execute="echo admin_setting('secure_path', hash('crc32b', config('app.key')));" 2>/dev/null | tail -1)

echo "ADMIN_PATH=\$ADMIN_PATH"

# 检查服务状态
docker compose ps
EOF
)

# 提取管理路径
ADMIN_PATH=$(echo "$INSTALL_OUTPUT" | grep "ADMIN_PATH=" | cut -d'=' -f2)

# 配置防火墙
echo -e "${BLUE}配置防火墙...${NC}"
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
# 开放端口
iptables -I INPUT -p tcp --dport ${PORT} -j ACCEPT

# 保存规则
iptables-save > /etc/iptables/rules.v4 2>/dev/null || true

echo "防火墙配置完成"
EOF

# 测试访问
echo -e "${BLUE}测试服务访问...${NC}"
sleep 5

HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' http://$SERVER_IP:$PORT/$ADMIN_PATH || echo "000")

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║            🎉 部署完成！                     ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}访问信息:${NC}"
echo "  管理后台: http://$SERVER_IP:$PORT/$ADMIN_PATH"
echo "  HTTP状态: $HTTP_CODE"
echo ""
echo -e "${BLUE}管理员账号:${NC}"
echo "  邮箱: $ADMIN_EMAIL"
echo "  密码: [请查看安装输出中的密码]"
echo ""
echo -e "${BLUE}服务管理:${NC}"
echo "  SSH登录: ssh root@$SERVER_IP"
echo "  查看日志: ssh root@$SERVER_IP 'cd /opt/Xboard && docker compose logs -f'"
echo "  重启服务: ssh root@$SERVER_IP 'cd /opt/Xboard && docker compose restart'"
echo ""

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✅ 服务正常运行！${NC}"
else
    echo -e "${YELLOW}⚠️  服务可能还在启动中，请稍等片刻后访问${NC}"
fi

# 保存部署信息
cat > deployment_info.txt << EOF
========================================
Xboard 部署信息
========================================
部署时间: $(date)
服务器IP: $SERVER_IP
部署端口: $PORT

访问地址:
  管理后台: http://$SERVER_IP:$PORT/$ADMIN_PATH

管理员账号:
  邮箱: $ADMIN_EMAIL
  密码: [查看安装输出]

服务管理:
  SSH登录: ssh root@$SERVER_IP
  项目目录: /opt/Xboard
  查看日志: docker compose logs -f
  重启服务: docker compose restart
EOF

echo -e "${GREEN}部署信息已保存到 deployment_info.txt${NC}"
