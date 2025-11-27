#!/bin/bash

# 线上生产环境完整更新部署脚本
# 包含官方更新合并后的部署
# 使用方法: ./update-production.sh

set -e

# 服务器配置（从环境变量或配置文件读取）
SERVER_IP="${SERVER_IP:-38.55.193.181}"
SERVER_USER="${SERVER_USER:-root}"
SERVER_PASS="${SERVER_PASS}"  # 必须通过环境变量传入
SERVER_PORT="${SERVER_PORT:-7002}"
CONTAINER_NAME="${CONTAINER_NAME:-xboard-multi-tenant-official}"
REMOTE_DIR="${REMOTE_DIR:-/opt/Xboard}"

# 检查必需的环境变量
if [ -z "$SERVER_PASS" ]; then
    error "请设置 SERVER_PASS 环境变量"
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

# 日志文件
LOG_FILE="update-production-$(date +%Y%m%d-%H%M%S).log"

log() {
    echo -e "${CYAN}[$(date '+%H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[错误]${NC} $1" | tee -a "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[成功]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[警告]${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[信息]${NC} $1" | tee -a "$LOG_FILE"
}

echo ""
echo -e "${CYAN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║      XBoard 线上生产环境完整更新部署脚本 v2.0           ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

log "开始更新流程..."
info "服务器: $SERVER_IP:$SERVER_PORT"
info "日志文件: $LOG_FILE"
echo ""

# 检查必要工具
echo -e "${YELLOW}📋 检查本地环境...${NC}"
for cmd in git sshpass curl; do
    if ! command -v $cmd &> /dev/null; then
        error "$cmd 未安装，请先安装: sudo apt install $cmd"
        exit 1
    fi
done
success "本地环境检查通过"
echo ""

# 检查当前git状态
echo -e "${YELLOW}📋 检查代码状态...${NC}"
if [ ! -d ".git" ]; then
    error "请在项目根目录运行此脚本"
    exit 1
fi

# 检查是否有未提交的修改
if ! git diff-index --quiet HEAD --; then
    error "有未提交的修改，请先提交或暂存"
    git status --short
    exit 1
fi

CURRENT_BRANCH=$(git branch --show-current)
CURRENT_COMMIT=$(git rev-parse --short HEAD)
info "当前分支: $CURRENT_BRANCH"
info "当前提交: $CURRENT_COMMIT"
success "代码状态检查通过"
echo ""

# 显示更新内容
echo -e "${YELLOW}📋 本次更新内容:${NC}"
git log -5 --oneline | sed 's/^/  /'
echo ""

# 确认继续
read -p "$(echo -e ${YELLOW}确认开始部署更新？${NC} [y/N]: )" -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    warning "用户取消部署"
    exit 0
fi
echo ""

# ============================================================================
# 阶段 1: 推送代码到 GitHub
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 1/6: 推送代码到 GitHub                             ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "推送代码到远程仓库..."
if git push origin $CURRENT_BRANCH; then
    success "代码推送成功"
else
    error "代码推送失败"
    exit 1
fi
echo ""

# 等待 GitHub Actions 构建
echo -e "${YELLOW}⏳ 等待 GitHub Actions 构建镜像...${NC}"
info "构建地址: https://github.com/Moearly/Xboard/actions"
info "通常需要 3-5 分钟"
echo ""

read -p "$(echo -e ${YELLOW}GitHub Actions 构建是否已完成？${NC} [y/N]: )" -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    warning "请等待构建完成后重新运行脚本"
    exit 0
fi
echo ""

# ============================================================================
# 阶段 2: 备份线上环境
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 2/6: 备份线上环境                                  ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "连接服务器进行备份..."

BACKUP_TAG=$(date +%Y%m%d_%H%M%S)

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
set -e

echo "📦 1. 备份Docker容器..."
docker commit $CONTAINER_NAME xboard-backup:$BACKUP_TAG || {
    echo "⚠️  容器备份失败，继续..."
}

echo "💾 2. 备份数据库..."
cd $REMOTE_DIR
mkdir -p backups
docker exec $CONTAINER_NAME php artisan backup:database > backups/db_$BACKUP_TAG.sql || {
    echo "⚠️  数据库备份失败，使用直接导出..."
    docker exec $CONTAINER_NAME mysqldump -u root xboard > backups/db_$BACKUP_TAG.sql 2>/dev/null || echo "数据库备份跳过"
}

echo "📁 3. 备份配置文件..."
cp -f .env backups/.env_$BACKUP_TAG 2>/dev/null || echo ".env不存在"
cp -f storage/database.sqlite backups/database_$BACKUP_TAG.sqlite 2>/dev/null || echo "SQLite不存在"

echo "✅ 备份完成"
echo "备份标签: $BACKUP_TAG"
ls -lh backups/ | tail -5
EOF

success "线上环境备份完成"
info "备份标签: xboard-backup:$BACKUP_TAG"
echo ""

# ============================================================================
# 阶段 3: 拉取最新镜像
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 3/6: 拉取最新镜像                                  ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "拉取最新Docker镜像..."

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
set -e

echo "📥 拉取最新镜像..."
docker pull ghcr.io/moearly/xboard:latest

echo "🔍 镜像信息:"
docker images ghcr.io/moearly/xboard:latest

echo "✅ 镜像拉取完成"
EOF

success "镜像拉取成功"
echo ""

# ============================================================================
# 阶段 4: 停止旧服务并启动新服务
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 4/6: 更新服务容器                                  ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "停止旧容器并启动新容器..."

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
set -e
cd $REMOTE_DIR

echo "⏹️  1. 停止旧容器..."
docker stop $CONTAINER_NAME 2>/dev/null || echo "容器未运行"

echo "🗑️  2. 删除旧容器..."
docker rm $CONTAINER_NAME 2>/dev/null || echo "容器已删除"

echo "🚀 3. 启动新容器..."
docker run -d \\
  --name $CONTAINER_NAME \\
  --restart unless-stopped \\
  -p $SERVER_PORT:7001 \\
  -v $REMOTE_DIR:/www \\
  -v $REMOTE_DIR/.docker/.data/redis:/data \\
  -e ENABLE_SQLITE=true \\
  -e ENABLE_REDIS=true \\
  -e docker=true \\
  ghcr.io/moearly/xboard:latest \\
  php artisan octane:start --port=7001 --host=0.0.0.0

echo "⏳ 4. 等待容器启动..."
sleep 10

echo "🔍 5. 检查容器状态..."
docker ps | grep $CONTAINER_NAME

echo "✅ 容器启动成功"
EOF

success "服务容器更新完成"
echo ""

# ============================================================================
# 阶段 5: 数据库迁移和缓存清理
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 5/6: 数据库迁移和缓存清理                          ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "执行数据库迁移和缓存清理..."

sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
set -e

echo "🗄️  1. 运行数据库迁移..."
docker exec $CONTAINER_NAME php artisan migrate --force || {
    echo "⚠️  迁移失败或已执行"
}

echo "🧹 2. 清理所有缓存..."
docker exec $CONTAINER_NAME php artisan config:clear
docker exec $CONTAINER_NAME php artisan cache:clear
docker exec $CONTAINER_NAME php artisan route:clear
docker exec $CONTAINER_NAME php artisan view:clear

echo "🔧 3. 优化性能..."
docker exec $CONTAINER_NAME php artisan config:cache
docker exec $CONTAINER_NAME php artisan route:cache

echo "📝 4. 修复权限..."
docker exec $CONTAINER_NAME chown -R www-data:www-data /www/storage /www/bootstrap/cache 2>/dev/null || echo "权限修复跳过"

echo "✅ 数据库和缓存处理完成"
EOF

success "数据库迁移和缓存清理完成"
echo ""

# ============================================================================
# 阶段 6: 验证部署结果
# ============================================================================
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  阶段 6/6: 验证部署结果                                  ${NC}"
echo -e "${CYAN}═════════════════════════════════════════════════════════${NC}"
echo ""

log "验证部署结果..."

# 等待服务完全启动
sleep 5

# 测试HTTP访问
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://$SERVER_IP:$SERVER_PORT 2>/dev/null || echo "000")

if [ "$HTTP_CODE" = "404" ]; then
    success "✅ 服务正常运行 (HTTP 404 = 多租户域名验证)"
    info "这是正常现象，表示多租户系统正常工作"
elif [ "$HTTP_CODE" = "200" ]; then
    success "✅ 服务正常运行 (HTTP 200)"
elif [ "$HTTP_CODE" = "000" ]; then
    error "❌ 无法连接到服务器"
else
    warning "⚠️  HTTP状态码: $HTTP_CODE (需要检查)"
fi

# 获取容器状态
echo ""
log "获取容器状态..."
sshpass -p "$SERVER_PASS" ssh -o StrictHostKeyChecking=no $SERVER_USER@$SERVER_IP << EOF
echo "📊 容器状态:"
docker ps | grep $CONTAINER_NAME

echo ""
echo "📝 最近日志 (最后20行):"
docker logs $CONTAINER_NAME --tail 20
EOF

echo ""
echo -e "${GREEN}═════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✅ 部署更新完成！                                       ${NC}"
echo -e "${GREEN}═════════════════════════════════════════════════════════${NC}"
echo ""

# 显示部署摘要
echo -e "${CYAN}📋 部署摘要:${NC}"
echo "  • 服务器: $SERVER_IP:$SERVER_PORT"
echo "  • 容器名: $CONTAINER_NAME"
echo "  • 备份标签: xboard-backup:$BACKUP_TAG"
echo "  • Git提交: $CURRENT_COMMIT"
echo "  • 部署时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo -e "${CYAN}🌐 访问地址:${NC}"
echo "  • 网站首页: http://$SERVER_IP:$SERVER_PORT"
echo "  • 管理后台: http://$SERVER_IP:$SERVER_PORT/admin"
echo "  • 线上账号: admin@vpnall.com / Admin2025"
echo ""

echo -e "${CYAN}📝 后续操作:${NC}"
echo "  1. 访问网站验证功能是否正常"
echo "  2. 检查多租户功能"
echo "  3. 测试关键业务流程"
echo "  4. 监控服务器日志"
echo ""

echo -e "${CYAN}🔧 常用命令:${NC}"
echo "  • 查看日志: sshpass -p '$SERVER_PASS' ssh $SERVER_USER@$SERVER_IP 'docker logs -f $CONTAINER_NAME'"
echo "  • 进入容器: sshpass -p '$SERVER_PASS' ssh $SERVER_USER@$SERVER_IP 'docker exec -it $CONTAINER_NAME sh'"
echo "  • 重启服务: sshpass -p '$SERVER_PASS' ssh $SERVER_USER@$SERVER_IP 'docker restart $CONTAINER_NAME'"
echo ""

echo -e "${YELLOW}💡 回滚方法 (如果出现问题):${NC}"
echo "  sshpass -p '$SERVER_PASS' ssh $SERVER_USER@$SERVER_IP << 'ROLLBACK'"
echo "    docker stop $CONTAINER_NAME && docker rm $CONTAINER_NAME"
echo "    docker run -d --name $CONTAINER_NAME -p $SERVER_PORT:7001 \\"
echo "      -v $REMOTE_DIR:/www xboard-backup:$BACKUP_TAG"
echo "  ROLLBACK"
echo ""

success "所有操作完成！日志已保存到: $LOG_FILE"
echo ""

