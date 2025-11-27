# 部署脚本使用说明

## 📦 deploy-production.sh - 线上生产环境部署

### 功能
- ✅ 自动备份（容器镜像、数据库、配置文件）
- ✅ 拉取最新镜像
- ✅ 停止旧服务、启动新服务
- ✅ 数据库迁移
- ✅ 缓存清理和性能优化
- ✅ 部署验证
- ✅ 记录部署日志

### 使用方法

```bash
# 前提：代码已推送到GitHub，GitHub Actions已构建完成

# 执行部署
SERVER_PASS='your_password' ./deployment-scripts/deploy-production.sh
```

### 部署流程

```
步骤 1/6: 备份当前数据
  ├── 备份容器镜像 → xboard-backup:YYYYMMDD_HHMMSS
  ├── 备份数据库 → backups/db_YYYYMMDD_HHMMSS.sql
  └── 备份配置 → backups/.env_YYYYMMDD_HHMMSS

步骤 2/6: 拉取最新镜像
  └── docker compose pull

步骤 3/6: 停止旧服务
  └── docker compose down

步骤 4/6: 启动新服务
  ├── docker compose up -d
  └── 等待20秒启动

步骤 5/6: 数据库迁移和缓存清理
  ├── php artisan migrate --force
  ├── 清理缓存（config/cache/route/view）
  └── 优化性能（config:cache/route:cache）

步骤 6/6: 验证部署结果
  ├── 检查容器状态
  ├── 测试HTTP访问
  └── 查看服务日志
```

### 备份信息

所有备份保存在服务器 `/opt/Xboard/backups/` 目录：

- `xboard-backup:YYYYMMDD_HHMMSS` - Docker镜像备份
- `db_YYYYMMDD_HHMMSS.sql` - 数据库备份
- `.env_YYYYMMDD_HHMMSS` - 配置文件备份
- `deploy_YYYYMMDD_HHMMSS.log` - 部署日志

### 回滚方法

如果部署出现问题，使用以下命令回滚：

```bash
ssh root@38.55.193.181 << 'EOF'
cd /opt/Xboard

# 停止服务
docker compose down

# 恢复备份镜像
docker tag xboard-backup:备份标签 ghcr.io/moearly/xboard:latest

# 启动服务
docker compose up -d
EOF
```

### 常用命令

```bash
# 查看服务日志
ssh root@38.55.193.181 'cd /opt/Xboard && docker compose logs -f web'

# 重启服务
ssh root@38.55.193.181 'cd /opt/Xboard && docker compose restart'

# 查看备份列表
ssh root@38.55.193.181 'ls -lh /opt/Xboard/backups/'

# 查看容器状态
ssh root@38.55.193.181 'cd /opt/Xboard && docker compose ps'
```

### 环境变量

| 变量 | 默认值 | 说明 |
|------|--------|------|
| SERVER_IP | 38.55.193.181 | 服务器IP |
| SERVER_USER | root | SSH用户名 |
| SERVER_PASS | 必填 | SSH密码 |
| REMOTE_DIR | /opt/Xboard | 项目目录 |

### 注意事项

1. **部署前检查**
   - 确保代码已推送到 GitHub
   - 确认 GitHub Actions 构建完成
   - 选择合适的时间窗口（低峰期）

2. **备份策略**
   - 每次部署自动创建完整备份
   - 备份标签使用时间戳
   - 建议定期清理旧备份

3. **失败处理**
   - 如果部署失败，使用备份标签快速回滚
   - 查看日志定位问题
   - 修复后重新部署

### 完整更新流程

```bash
# 1. 本地：合并官方更新（如需要）
./scripts/merge-official.sh

# 2. 本地：推送代码
git push origin master

# 3. GitHub：等待 Actions 构建（3-5分钟）
# 访问：https://github.com/Moearly/Xboard/actions

# 4. 本地：部署到线上
SERVER_PASS='password' ./deployment-scripts/deploy-production.sh

# 5. 验证：访问测试
# http://38.55.193.181:7002
```

