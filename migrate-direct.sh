#!/bin/bash

##############################################################################
# XBoard 多站点系统 - 直接迁移脚本（无交互）
##############################################################################

set -e

echo "🚀 开始执行数据库迁移..."
echo ""

# 进入xboard目录
cd "$(dirname "$0")"

# 检查环境
if [ ! -f "artisan" ]; then
    echo "❌ 错误：请在xboard目录下运行此脚本"
    exit 1
fi

echo "✅ 目录检查通过"

# 检查迁移文件
echo "📋 检查迁移文件..."
MIGRATION_COUNT=$(ls -1 database/migrations/*tenant* database/migrations/*create_tenants* 2>/dev/null | wc -l)
echo "找到 $MIGRATION_COUNT 个租户相关迁移文件"

# 尝试不同的执行方式

echo ""
echo "🔧 尝试执行迁移..."
echo ""

# 方式1：直接PHP
if command -v php &> /dev/null; then
    echo "使用本地PHP执行..."
    php artisan migrate --force
    echo ""
    echo "✅ 迁移完成！"
    exit 0
fi

# 方式2：使用Docker
if command -v docker &> /dev/null; then
    echo "查找Docker容器..."
    
    # 尝试常见的容器名称
    for container in $(docker ps --format '{{.Names}}'); do
        if echo "$container" | grep -qE 'php|xboard|laravel|app'; then
            echo "找到容器：$container"
            echo "在容器中执行迁移..."
            docker exec "$container" php artisan migrate --force
            echo ""
            echo "✅ 迁移完成！"
            exit 0
        fi
    done
    
    echo "⚠️  未找到合适的PHP容器"
fi

# 如果都失败
echo ""
echo "❌ 无法自动执行迁移"
echo ""
echo "请手动执行以下命令之一："
echo ""
echo "  方式1（本地PHP）："
echo "    cd /home/martnlei/codeSpace/XboradAll/VpnAll/xboard"
echo "    php artisan migrate --force"
echo ""
echo "  方式2（Docker）："
echo "    docker exec -it [容器名] php artisan migrate --force"
echo ""

exit 1

