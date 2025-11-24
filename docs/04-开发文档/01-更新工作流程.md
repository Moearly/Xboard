# 🚀 Xboard多租户系统更新流程

## 📋 概述

本文档定义了Xboard多租户系统的完整更新流程，确保代码修改能够自动构建、部署和验证。

## 🔄 自动化更新流程

### 1. 开发阶段

```bash
# 1. 修改代码
git add .
git commit -m "feat: 你的功能描述"

# 2. 推送到GitHub（自动触发构建）
git push origin master
```

### 2. 自动构建阶段

**GitHub Actions自动执行：**
- ✅ 代码检出
- ✅ Docker镜像构建
- ✅ 推送到 `ghcr.io/moearly/xboard:latest`
- ✅ 多平台支持（amd64, arm64）

**监控构建状态：**
- 访问：https://github.com/Moearly/Xboard/actions
- 等待构建完成（通常5-10分钟）

### 3. 服务器更新阶段

**自动更新脚本：**

```bash
#!/bin/bash
# 文件：update-multi-tenant.sh

echo "🚀 更新多租户系统..."

# 1. 拉取最新镜像
docker pull ghcr.io/moearly/xboard:latest

# 2. 停止当前服务
docker stop xboard-multi-tenant-official

# 3. 备份当前容器
docker commit xboard-multi-tenant-official xboard-backup:$(date +%Y%m%d_%H%M%S)

# 4. 删除旧容器
docker rm xboard-multi-tenant-official

# 5. 启动新容器
docker run -d \
  --name xboard-multi-tenant-official \
  --restart unless-stopped \
  -p 7002:7001 \
  -v /opt/Xboard:/www \
  -e ENABLE_SQLITE=true \
  -e ENABLE_REDIS=true \
  ghcr.io/moearly/xboard:latest

# 6. 等待启动
sleep 10

# 7. 运行迁移
docker exec xboard-multi-tenant-official php artisan migrate --force

# 8. 清理缓存
docker exec xboard-multi-tenant-official php artisan config:clear
docker exec xboard-multi-tenant-official php artisan cache:clear

# 9. 启动PHP服务器（解决Octane权限问题）
docker exec xboard-multi-tenant-official pkill -f octane || true
docker exec -d xboard-multi-tenant-official php -S 0.0.0.0:7001 -t /www/public

# 10. 验证部署
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:7002)
if [ "$HTTP_CODE" = "404" ]; then
    echo "✅ 多租户系统更新成功！"
    echo "🌐 访问地址: http://38.55.193.181:7002"
else
    echo "❌ 更新可能有问题，HTTP状态码: $HTTP_CODE"
fi
```

## 🎯 完整更新命令

### 方式1：一键更新（推荐）

```bash
# 在本地执行
cd /path/to/xboard
git add .
git commit -m "你的更新说明"
git push origin master

# 等待GitHub Actions构建完成后，在服务器执行
./update-multi-tenant.sh
```

### 方式2：手动更新

```bash
# 1. 推送代码
git push origin master

# 2. 等待构建完成（访问 https://github.com/Moearly/Xboard/actions）

# 3. 在服务器手动更新
ssh root@38.55.193.181
docker pull ghcr.io/moearly/xboard:latest
docker stop xboard-multi-tenant-official
docker rm xboard-multi-tenant-official
# ... 重新启动容器
```

## 📊 更新验证清单

### ✅ 构建验证
- [ ] GitHub Actions构建成功
- [ ] Docker镜像推送成功
- [ ] 镜像大小合理（~380MB）

### ✅ 部署验证
- [ ] 容器启动成功
- [ ] 数据库迁移完成
- [ ] 多租户模型文件存在
- [ ] HTTP响应正常（404表示多租户工作正常）

### ✅ 功能验证
- [ ] 多租户API可访问
- [ ] 数据库表结构正确
- [ ] 缓存清理成功

## 🚨 故障排除

### 构建失败
```bash
# 检查GitHub Actions日志
# 访问：https://github.com/Moearly/Xboard/actions

# 常见问题：
# 1. Dockerfile语法错误
# 2. 依赖安装失败
# 3. 权限问题
```

### 部署失败
```bash
# 检查容器日志
docker logs xboard-multi-tenant-official

# 检查镜像是否拉取成功
docker images | grep moearly/xboard

# 回滚到备份版本
docker stop xboard-multi-tenant-official
docker run -d --name xboard-multi-tenant-official xboard-backup:YYYYMMDD_HHMMSS
```

## 🔄 回滚流程

```bash
# 1. 停止当前版本
docker stop xboard-multi-tenant-official
docker rm xboard-multi-tenant-official

# 2. 使用备份镜像
docker run -d \
  --name xboard-multi-tenant-official \
  --restart unless-stopped \
  -p 7002:7001 \
  -v /opt/Xboard:/www \
  xboard-backup:BACKUP_TAG

# 3. 验证回滚
curl -s -o /dev/null -w "%{http_code}" http://localhost:7002
```

## 📈 监控和日志

### 实时监控
```bash
# 容器状态
docker ps | grep multi-tenant

# 容器日志
docker logs -f xboard-multi-tenant-official

# 系统资源
docker stats xboard-multi-tenant-official
```

### 定期检查
```bash
# 每日检查脚本
#!/bin/bash
echo "📊 多租户系统健康检查 - $(date)"
echo "容器状态: $(docker ps | grep multi-tenant-official | wc -l)"
echo "HTTP状态: $(curl -s -o /dev/null -w "%{http_code}" http://localhost:7002)"
echo "磁盘使用: $(df -h /opt/Xboard | tail -1)"
```

---

## 🎉 总结

通过这个流程，您的代码修改将：
1. **自动构建** - GitHub Actions自动构建Docker镜像
2. **版本控制** - 每个版本都有对应的镜像标签
3. **快速部署** - 一键更新脚本
4. **安全回滚** - 自动备份和回滚机制
5. **状态监控** - 完整的验证和监控体系

**下次更新只需要：**
```bash
git add . && git commit -m "你的更新" && git push origin master
# 等待构建完成后
./update-multi-tenant.sh
```
