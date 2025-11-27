#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

echo "📥 XBoard 官方更新合并脚本"
echo "================================"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 1. 检查当前分支
echo -e "${BLUE}🔍 检查当前分支...${NC}"
CURRENT_BRANCH=$(git branch --show-current)
if [ "$CURRENT_BRANCH" != "multi-tenant" ]; then
    echo -e "${RED}❌ 错误: 请先切换到 multi-tenant 分支${NC}"
    echo "   git checkout multi-tenant"
    exit 1
fi
echo -e "${GREEN}✅ 当前在 multi-tenant 分支${NC}"
echo ""

# 2. 检查是否有未提交的修改
echo -e "${BLUE}🔍 检查未提交的修改...${NC}"
if ! git diff-index --quiet HEAD --; then
    echo -e "${RED}❌ 错误: 有未提交的修改${NC}"
    echo ""
    echo "未提交的文件:"
    git status --short
    echo ""
    echo "请先提交或暂存修改:"
    echo "  git stash save \"临时保存: $(date +%Y%m%d-%H%M%S)\""
    exit 1
fi
echo -e "${GREEN}✅ 工作目录干净${NC}"
echo ""

# 3. 检查 upstream 远程仓库
echo -e "${BLUE}🔍 检查 upstream 远程仓库...${NC}"
if ! git remote | grep -q "^upstream$"; then
    echo -e "${YELLOW}⚠️  未找到 upstream 远程仓库，正在添加...${NC}"
    git remote add upstream https://github.com/cedar2025/Xboard.git
    echo -e "${GREEN}✅ 已添加 upstream 远程仓库${NC}"
else
    echo -e "${GREEN}✅ upstream 远程仓库已存在${NC}"
fi
echo ""

# 4. 拉取官方更新
echo -e "${BLUE}📡 拉取官方更新...${NC}"
git fetch upstream main
echo -e "${GREEN}✅ 拉取完成${NC}"
echo ""

# 5. 显示更新内容
echo -e "${BLUE}📋 官方更新内容:${NC}"
echo "---"
COMMIT_COUNT=$(git log HEAD..upstream/main --oneline --no-merges | wc -l)
echo "新增提交数: $COMMIT_COUNT"
echo ""
git log HEAD..upstream/main --oneline --no-merges | head -10
if [ $COMMIT_COUNT -gt 10 ]; then
    echo "... 还有 $((COMMIT_COUNT - 10)) 个提交"
fi
echo "---"
echo ""

# 6. 显示修改的文件
echo -e "${BLUE}📁 修改的文件统计:${NC}"
git diff --stat HEAD..upstream/main | tail -1
echo ""

# 7. 询问是否继续
read -p "$(echo -e ${YELLOW}是否继续合并? \(y/N\) ${NC})" -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ 取消合并${NC}"
    exit 0
fi
echo ""

# 8. 创建合并分支
MERGE_BRANCH="merge-official-$(date +%Y%m%d-%H%M%S)"
echo -e "${BLUE}🔀 创建合并分支: $MERGE_BRANCH${NC}"
git checkout -b "$MERGE_BRANCH"
echo -e "${GREEN}✅ 分支创建成功${NC}"
echo ""

# 9. 尝试合并
echo -e "${BLUE}🔄 开始合并...${NC}"
if git merge upstream/main --no-ff -m "merge: 合并官方 XBoard 更新 ($(date +%Y-%m-%d))"; then
    echo ""
    echo -e "${GREEN}✅ 自动合并成功！${NC}"
    echo ""
    
    # 10. 运行测试
    echo -e "${BLUE}🧪 运行测试...${NC}"
    if [ -f "./test-scripts/real-world-test.sh" ]; then
        if ./test-scripts/real-world-test.sh; then
            echo ""
            echo -e "${GREEN}✅ 测试通过！${NC}"
            echo ""
            
            # 11. 合并到主分支
            echo -e "${BLUE}📦 合并到 multi-tenant 分支...${NC}"
            git checkout multi-tenant
            git merge "$MERGE_BRANCH" --no-ff -m "merge: 完成官方更新合并 ($(date +%Y-%m-%d))"
            
            echo ""
            echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            echo -e "${GREEN}✅ 合并完成！${NC}"
            echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            echo ""
            echo -e "${YELLOW}下一步:${NC}"
            echo "  1. 推送到远程: ${BLUE}git push origin multi-tenant${NC}"
            echo "  2. 删除临时分支: ${BLUE}git branch -d $MERGE_BRANCH${NC}"
            echo ""
            echo -e "${YELLOW}📝 建议记录合并信息到: tenant/合并记录.md${NC}"
            
        else
            echo ""
            echo -e "${RED}❌ 测试失败${NC}"
            echo ""
            echo -e "${YELLOW}当前在分支: $MERGE_BRANCH${NC}"
            echo ""
            echo "请检查测试失败的原因:"
            echo "  1. 查看日志: ${BLUE}tail -f storage/logs/laravel.log${NC}"
            echo "  2. 清理缓存: ${BLUE}php artisan cache:clear${NC}"
            echo "  3. 检查配置: ${BLUE}php artisan tinker${NC}"
            echo ""
            echo "修复后重新测试:"
            echo "  ${BLUE}./test-scripts/real-world-test.sh${NC}"
            echo ""
            echo "测试通过后继续:"
            echo "  ${BLUE}git checkout multi-tenant${NC}"
            echo "  ${BLUE}git merge $MERGE_BRANCH${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠️  测试脚本不存在，跳过自动测试${NC}"
        echo ""
        echo -e "${YELLOW}请手动测试关键功能后继续:${NC}"
        echo "  1. 租户识别"
        echo "  2. 数据隔离"
        echo "  3. 配置读取"
        echo "  4. 用户注册"
        echo "  5. 订单创建"
        echo ""
        echo "测试通过后:"
        echo "  ${BLUE}git checkout multi-tenant${NC}"
        echo "  ${BLUE}git merge $MERGE_BRANCH${NC}"
    fi
else
    echo ""
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}⚠️  发现冲突，需要手动解决${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${RED}冲突文件列表:${NC}"
    git diff --name-only --diff-filter=U | while read file; do
        echo "  - $file"
    done
    echo ""
    echo -e "${YELLOW}解决步骤:${NC}"
    echo "  1. 编辑冲突文件（参考下方文档）"
    echo "  2. 标记已解决: ${BLUE}git add <文件>${NC}"
    echo "  3. 完成合并: ${BLUE}git commit${NC}"
    echo "  4. 运行测试: ${BLUE}./test-scripts/real-world-test.sh${NC}"
    echo "  5. 合并到主分支: ${BLUE}git checkout multi-tenant && git merge $MERGE_BRANCH${NC}"
    echo ""
    echo -e "${BLUE}📖 参考文档:${NC}"
    echo "  - docs/04-开发文档/官方更新合并指南.md"
    echo "  - tenant/19-Git提交记录与插件化可行性分析.md"
    echo "  - tenant/20-官方更新合并快速参考.md"
    echo ""
    echo -e "${YELLOW}💡 常见冲突快速解决:${NC}"
    echo ""
    echo "  ${BLUE}模型文件冲突:${NC}"
    echo "    保留 'use BelongsToTenant;' + 官方新增内容"
    echo ""
    echo "  ${BLUE}配置文件冲突:${NC}"
    echo "    仔细对比，合并双方优点"
    echo ""
    echo "  ${BLUE}中间件冲突:${NC}"
    echo "    优先保留我们的租户识别逻辑"
    echo ""
    exit 1
fi

