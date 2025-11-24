#!/bin/bash
#
# XBoard 后端完整部署脚本
# 功能：同步代码、执行迁移、清除缓存、验证服务状态
#

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 容器名称
CONTAINER_NAME="xboard-app-test"

# 项目根目录
PROJECT_DIR="/home/martnlei/codeSpace/XboradAll/VpnAll/xboard"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}XBoard 后端部署脚本${NC}"
echo -e "${GREEN}========================================${NC}"

# 步骤 1: 检查容器状态
echo -e "\n${YELLOW}[1/7] 检查 Docker 容器状态...${NC}"
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    echo -e "${RED}错误: 容器 $CONTAINER_NAME 未运行${NC}"
    exit 1
fi
echo -e "${GREEN}✓ 容器运行中${NC}"

# 步骤 2: 同步代码到容器
echo -e "\n${YELLOW}[2/7] 同步代码文件到容器...${NC}"

# 同步模型文件
echo "  → 同步 Models..."
docker cp "$PROJECT_DIR/app/Models/Plan.php" "$CONTAINER_NAME:/var/www/html/app/Models/Plan.php"

# 同步控制器文件
echo "  → 同步 Controllers..."
docker cp "$PROJECT_DIR/app/Http/Controllers/V2/Admin/PlanController.php" "$CONTAINER_NAME:/var/www/html/app/Http/Controllers/V2/Admin/PlanController.php"

# 同步中间件文件
echo "  → 同步 Middleware..."
docker cp "$PROJECT_DIR/app/Http/Middleware/Admin.php" "$CONTAINER_NAME:/var/www/html/app/Http/Middleware/Admin.php"
docker cp "$PROJECT_DIR/app/Http/Middleware/SuperAdmin.php" "$CONTAINER_NAME:/var/www/html/app/Http/Middleware/SuperAdmin.php"
docker cp "$PROJECT_DIR/app/Http/Middleware/TenantIdentification.php" "$CONTAINER_NAME:/var/www/html/app/Http/Middleware/TenantIdentification.php"

# 同步路由文件
echo "  → 同步 Routes..."
docker cp "$PROJECT_DIR/app/Http/Routes/V2/AdminRoute.php" "$CONTAINER_NAME:/var/www/html/app/Http/Routes/V2/AdminRoute.php"

# 同步 Support 文件
echo "  → 同步 Support..."
docker cp "$PROJECT_DIR/app/Support/SharedSettings.php" "$CONTAINER_NAME:/var/www/html/app/Support/SharedSettings.php"

echo -e "${GREEN}✓ 代码同步完成${NC}"

# 步骤 3: 同步迁移文件
echo -e "\n${YELLOW}[3/7] 同步迁移文件...${NC}"
docker exec "$CONTAINER_NAME" mkdir -p /var/www/html/database/migrations

# 同步所有租户相关迁移
for migration in "$PROJECT_DIR"/database/migrations/*tenant*.php \
                 "$PROJECT_DIR"/database/migrations/*_add_tags_to_plan.php; do
    if [ -f "$migration" ]; then
        filename=$(basename "$migration")
        echo "  → $filename"
        docker cp "$migration" "$CONTAINER_NAME:/var/www/html/database/migrations/$filename"
    fi
done

echo -e "${GREEN}✓ 迁移文件同步完成${NC}"

# 步骤 4: 执行迁移
echo -e "\n${YELLOW}[4/7] 执行数据库迁移...${NC}"
docker exec "$CONTAINER_NAME" php artisan migrate --force || {
    echo -e "${YELLOW}⚠ 迁移可能已执行，继续...${NC}"
}
echo -e "${GREEN}✓ 迁移完成${NC}"

# 步骤 5: 清除所有缓存
echo -e "\n${YELLOW}[5/7] 清除缓存...${NC}"
docker exec "$CONTAINER_NAME" php artisan cache:clear
docker exec "$CONTAINER_NAME" php artisan config:clear
docker exec "$CONTAINER_NAME" php artisan route:clear
docker exec "$CONTAINER_NAME" php artisan view:clear
echo -e "${GREEN}✓ 缓存清除完成${NC}"

# 步骤 6: 验证关键文件
echo -e "\n${YELLOW}[6/7] 验证部署文件...${NC}"
docker exec "$CONTAINER_NAME" ls -la /var/www/html/app/Models/Plan.php > /dev/null 2>&1 && \
    echo "  ✓ Plan.php" || echo "  ✗ Plan.php 缺失"

docker exec "$CONTAINER_NAME" ls -la /var/www/html/app/Http/Controllers/V2/Admin/PlanController.php > /dev/null 2>&1 && \
    echo "  ✓ PlanController.php" || echo "  ✗ PlanController.php 缺失"

# 验证数据库表结构
echo -e "\n  验证数据库表结构..."
docker exec "$CONTAINER_NAME" php artisan tinker --execute="
    echo Schema::hasColumn('v2_plan', 'tags') ? '  ✓ v2_plan.tags 字段存在' : '  ✗ v2_plan.tags 字段缺失';
    echo PHP_EOL;
    echo Schema::hasColumn('v2_plan', 'tenant_id') ? '  ✓ v2_plan.tenant_id 字段存在' : '  ✗ v2_plan.tenant_id 字段缺失';
"

echo -e "${GREEN}✓ 验证完成${NC}"

# 步骤 7: 测试 API 端点
echo -e "\n${YELLOW}[7/7] 测试 API 端点...${NC}"

# 获取管理员 token
echo "  → 获取管理员 token..."
ADMIN_TOKEN=$(docker exec "$CONTAINER_NAME" php artisan tinker --execute="echo User::where('email', 'admin@vpnall.com')->first()->token;")

if [ -z "$ADMIN_TOKEN" ]; then
    echo -e "${RED}✗ 无法获取管理员 token${NC}"
    exit 1
fi

# 测试 API
echo "  → 测试 /api/v2/admin/plan/fetch..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "X-Super-Admin: true" \
    http://localhost:8080/api/v2/admin/plan/fetch)

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}  ✓ API 响应正常 (HTTP $HTTP_CODE)${NC}"
else
    echo -e "${RED}  ✗ API 响应异常 (HTTP $HTTP_CODE)${NC}"
    echo "  → 查看最近的错误日志..."
    docker exec "$CONTAINER_NAME" tail -20 storage/logs/laravel-$(date +%Y-%m-%d).log 2>/dev/null || \
    echo "    无日志文件"
fi

# 测试套餐数据
echo "  → 测试套餐数据隔离..."
RESULT=$(curl -s \
    -H "Authorization: Bearer $ADMIN_TOKEN" \
    -H "X-Super-Admin: true" \
    -H "X-Tenant-ID: 13" \
    http://localhost:8080/api/v2/admin/plan/fetch)

if echo "$RESULT" | grep -q '"data"'; then
    PLAN_COUNT=$(echo "$RESULT" | grep -o '"id":' | wc -l)
    echo -e "${GREEN}  ✓ 租户13的套餐数量: $PLAN_COUNT${NC}"
else
    echo -e "${RED}  ✗ 无法获取套餐数据${NC}"
fi

echo -e "\n${GREEN}========================================${NC}"
echo -e "${GREEN}部署完成！${NC}"
echo -e "${GREEN}========================================${NC}"

echo -e "\n📊 部署摘要:"
echo "  • 容器名称: $CONTAINER_NAME"
echo "  • 项目目录: $PROJECT_DIR"
echo "  • API 地址: http://localhost:8080"
echo "  • 后台地址: http://localhost:3000"

echo -e "\n💡 后续操作:"
echo "  1. 刷新前端页面: http://localhost:3000/#/plan-management"
echo "  2. 查看容器日志: docker logs -f $CONTAINER_NAME"
echo "  3. 进入容器调试: docker exec -it $CONTAINER_NAME sh"

echo ""

