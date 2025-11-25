# XBoard 部署脚本目录

本目录存放所有部署和运维相关的脚本文件。

## 📁 脚本分类

### 部署脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `deploy-multi-tenant.sh` | 多租户部署 | 部署多租户版本的XBoard |
| `xboard-official-deploy.sh` | 官方部署脚本 | XBoard官方部署脚本 |
| `wait-and-deploy.sh` | 等待并部署 | 等待服务就绪后部署 |

### 数据迁移脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `migrate-tenant.sh` | 租户数据迁移 | 迁移单租户数据到多租户架构 |
| `migrate-direct.sh` | 直接迁移 | 直接运行数据库迁移 |

### 更新脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `update-multi-tenant.sh` | 多租户更新 | 更新多租户版本 |

### API检查脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `check-xboard-api.sh` | API健康检查 | 检查XBoard API是否正常 |

## 🚀 部署流程

### 1. 全新部署 (推荐)

```bash
# 1. 克隆代码
git clone <repository> xboard
cd xboard

# 2. 配置环境
cp .env.example .env
# 编辑 .env 文件

# 3. 运行部署脚本
./deployment-scripts/deploy-multi-tenant.sh
```

### 2. 从单租户迁移

```bash
# 1. 备份数据
mysqldump -u root -p xboard > backup.sql

# 2. 运行迁移脚本
./deployment-scripts/migrate-tenant.sh

# 3. 验证迁移结果
php artisan tinker
>>> \App\Models\Tenant::count();
>>> \App\Models\User::where('tenant_id', 1)->count();
```

### 3. 系统更新

```bash
# 拉取最新代码
git pull origin main

# 运行更新脚本
./deployment-scripts/update-multi-tenant.sh
```

## 📋 脚本详解

### deploy-multi-tenant.sh

**用途**: 部署多租户版本的XBoard

**执行内容**:
1. 检查环境依赖 (PHP, Composer, Node.js等)
2. 安装 Composer 依赖
3. 运行数据库迁移
4. 初始化默认租户
5. 编译前端资源
6. 配置权限
7. 启动服务

**使用方法**:
```bash
./deployment-scripts/deploy-multi-tenant.sh
```

**环境要求**:
- PHP >= 8.0
- Composer
- MySQL/MariaDB
- Node.js >= 14 (如需编译前端)

---

### migrate-tenant.sh

**用途**: 从单租户架构迁移到多租户架构

**执行内容**:
1. 备份现有数据
2. 运行多租户迁移脚本
3. 创建默认租户
4. 迁移现有数据到默认租户 (tenant_id = 1)
5. 更新索引
6. 验证数据完整性

**使用方法**:
```bash
# 交互式迁移
./deployment-scripts/migrate-tenant.sh

# 自动迁移(跳过确认)
./deployment-scripts/migrate-tenant.sh --auto
```

**⚠️ 重要提示**:
- 迁移前务必备份数据库
- 建议在测试环境先试运行
- 迁移过程中会修改数据库结构

---

### migrate-direct.sh

**用途**: 直接运行Laravel迁移

**执行内容**:
1. 运行 `php artisan migrate --force`
2. 不包含数据迁移逻辑

**使用方法**:
```bash
./deployment-scripts/migrate-direct.sh
```

**适用场景**:
- 全新安装
- 数据库表不存在
- 仅需更新表结构

---

### update-multi-tenant.sh

**用途**: 更新多租户系统

**执行内容**:
1. 进入维护模式
2. 拉取最新代码
3. 更新 Composer 依赖
4. 运行数据库迁移
5. 清理缓存
6. 退出维护模式

**使用方法**:
```bash
./deployment-scripts/update-multi-tenant.sh
```

**注意**:
- 更新期间网站会暂时不可用
- 更新前会自动备份

---

### check-xboard-api.sh

**用途**: 检查XBoard API健康状态

**执行内容**:
1. 检查HTTP服务是否响应
2. 测试关键API端点
3. 验证租户识别
4. 检查数据库连接

**使用方法**:
```bash
# 检查本地服务
./deployment-scripts/check-xboard-api.sh

# 检查远程服务
./deployment-scripts/check-xboard-api.sh https://api.example.com
```

**输出示例**:
```
✓ HTTP服务正常
✓ API响应正常
✓ 租户识别正常
✓ 数据库连接正常
```

---

### xboard-official-deploy.sh

**用途**: XBoard官方部署脚本

**说明**: 这是XBoard官方的部署脚本,用于单租户版本的部署。

**使用方法**:
```bash
./deployment-scripts/xboard-official-deploy.sh
```

**注意**: 如果要部署多租户版本,请使用 `deploy-multi-tenant.sh`

---

### wait-and-deploy.sh

**用途**: 等待依赖服务就绪后再部署

**执行内容**:
1. 等待数据库服务就绪
2. 等待Redis服务就绪 (如配置)
3. 运行部署脚本

**使用方法**:
```bash
# 在Docker环境中特别有用
./deployment-scripts/wait-and-deploy.sh
```

**配置**:
```bash
# 编辑脚本修改等待的服务
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
REDIS_HOST=${REDIS_HOST:-127.0.0.1}
REDIS_PORT=${REDIS_PORT:-6379}
```

## 🔧 常见问题

### 问题1: 权限错误

```bash
# 解决方法
chmod +x deployment-scripts/*.sh
```

### 问题2: 数据库连接失败

```bash
# 检查 .env 配置
cat .env | grep DB_

# 测试连接
mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD $DB_DATABASE
```

### 问题3: 迁移失败

```bash
# 回滚迁移
php artisan migrate:rollback

# 从备份恢复
mysql -u root -p xboard < backup.sql

# 重新运行迁移
php artisan migrate --force
```

### 问题4: Composer依赖安装失败

```bash
# 清理缓存
composer clear-cache

# 使用国内镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 重新安装
composer install
```

## 📊 部署检查清单

部署前检查:
- [ ] PHP版本 >= 8.0
- [ ] Composer已安装
- [ ] MySQL/MariaDB运行正常
- [ ] .env文件已配置
- [ ] 数据库已创建
- [ ] 文件权限正确 (storage/, bootstrap/cache/)

部署后验证:
- [ ] 网站可以访问
- [ ] API响应正常
- [ ] 数据库表已创建
- [ ] 默认租户已创建
- [ ] 日志无错误

## 🎯 最佳实践

### 1. 部署前

```bash
# 1. 备份数据
mysqldump -u root -p xboard > backup-$(date +%Y%m%d).sql

# 2. 备份代码
tar -czf xboard-backup-$(date +%Y%m%d).tar.gz .

# 3. 测试环境验证
# 在测试环境先运行一遍
```

### 2. 部署中

```bash
# 使用维护模式
php artisan down

# 部署操作...

# 退出维护模式
php artisan up
```

### 3. 部署后

```bash
# 1. 检查API
./deployment-scripts/check-xboard-api.sh

# 2. 查看日志
tail -f storage/logs/laravel.log

# 3. 测试核心功能
# - 用户注册
# - 订单创建
# - 节点访问
```

## 📝 添加新脚本

如果需要添加新的部署脚本:

1. 创建脚本文件
2. 添加执行权限 `chmod +x script.sh`
3. 更新此 README
4. 添加使用文档
5. 提交到版本控制

## 🔐 安全注意事项

1. **不要提交敏感信息**
   - .env 文件不要提交
   - 数据库密码不要硬编码
   - API密钥从环境变量读取

2. **权限控制**
   - 脚本只给必要的执行权限
   - 部署用户使用非root账户
   - 数据库用户使用最小权限

3. **备份策略**
   - 部署前必须备份
   - 保留多个历史备份
   - 定期测试备份恢复

---

**最后更新**: 2024-11-25  
**维护者**: VpnAll Team

