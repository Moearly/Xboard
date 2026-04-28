#!/bin/bash

#############################################
# 部署知识库编辑修复到线上
# 功能：只更新 admin.js 和 index8.js
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

echo "🚀 开始部署知识库编辑修复..."

# 本地文件路径
LOCAL_ADMIN_JS="/home/martnlei/codeSpace/XboradAll/VpnAll/xboard/public/assets/admin/assets/admin.js"
LOCAL_INDEX8_JS="/home/martnlei/codeSpace/XboradAll/VpnAll/xboard/public/assets/admin/assets/index8.js"

# 远程文件路径
REMOTE_ADMIN_JS="$REMOTE_DIR/public/assets/admin/assets/admin.js"
REMOTE_INDEX8_JS="$REMOTE_DIR/public/assets/admin/assets/index8.js"

# 使用 sshpass 上传文件
echo "📤 上传 admin.js..."
sshpass -p "$SERVER_PASS" scp "$LOCAL_ADMIN_JS" "$SERVER_USER@$SERVER_IP:$REMOTE_ADMIN_JS"

echo "📤 上传 index8.js..."
sshpass -p "$SERVER_PASS" scp "$LOCAL_INDEX8_JS" "$SERVER_USER@$SERVER_IP:$REMOTE_INDEX8_JS"

echo "✅ 部署完成！"
echo ""
echo "请刷新浏览器缓存后测试知识库编辑功能"

