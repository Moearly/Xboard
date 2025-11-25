# XBoard 测试脚本目录

本目录存放所有测试相关的脚本文件。

## 📁 脚本分类

### PHP 测试脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `test-plan-create.php` | 套餐创建测试 | 测试多租户环境下的套餐创建功能 |
| `test-shared-nodes.php` | 共享节点测试 | 测试节点共享机制 |
| `test-tenant-header.php` | 租户头测试 | 测试 X-Tenant-ID 请求头识别 |
| `test-config-isolation.php` | 配置隔离测试 | 测试租户配置隔离功能 |
| `create-test-tenants.php` | 创建测试租户 | 批量创建测试用的租户数据 |
| `test_clear_all_logs.php` | 清理日志 | 清理测试产生的日志文件 |

### Shell 测试脚本

| 文件名 | 说明 | 用途 |
|-------|------|------|
| `test-api-multi-tenant.sh` | 多租户API测试 | 测试多租户API隔离 |
| `test-multi-tenant-api.sh` | 多租户API测试(备用) | 另一个API测试版本 |
| `docker-test.sh` | Docker环境测试 | 在Docker环境中运行测试 |
| `real-world-test.sh` | 真实环境测试 | 模拟真实场景的综合测试 |

## 🚀 使用方法

### PHP 测试脚本

```bash
# 进入 xboard 目录
cd /path/to/xboard

# 运行测试脚本
php test-scripts/test-plan-create.php
php test-scripts/test-shared-nodes.php
php test-scripts/test-tenant-header.php
```

### Shell 测试脚本

```bash
# 进入 xboard 目录
cd /path/to/xboard

# 赋予执行权限(如果需要)
chmod +x test-scripts/*.sh

# 运行测试脚本
./test-scripts/docker-test.sh
./test-scripts/real-world-test.sh
```

## 📋 测试场景

### 1. 数据隔离测试

```bash
# 测试租户数据隔离
php test-scripts/test-config-isolation.php

# 测试共享节点
php test-scripts/test-shared-nodes.php
```

### 2. API 测试

```bash
# 测试多租户API
./test-scripts/test-api-multi-tenant.sh

# 测试租户识别
php test-scripts/test-tenant-header.php
```

### 3. 创建测试数据

```bash
# 创建测试租户
php test-scripts/create-test-tenants.php

# 创建测试套餐
php test-scripts/test-plan-create.php
```

### 4. 环境测试

```bash
# Docker环境测试
./test-scripts/docker-test.sh

# 真实环境综合测试
./test-scripts/real-world-test.sh
```

## 🔧 测试准备

运行测试前需要:

1. **配置数据库**
```bash
# 确保数据库配置正确
cp .env.example .env
# 编辑 .env 文件配置数据库连接
```

2. **运行迁移**
```bash
php artisan migrate
```

3. **创建测试租户**
```bash
php test-scripts/create-test-tenants.php
```

## 📊 测试覆盖

### 已覆盖的功能

✅ 租户识别 (域名/Header)  
✅ 数据隔离 (User, Order, Plan等)  
✅ 配置隔离 (共享配置 vs 独立配置)  
✅ 节点共享机制  
✅ 多租户API  
✅ 套餐管理  

### 待补充的测试

⏳ 支付回调测试  
⏳ 工单系统测试  
⏳ 统计数据隔离测试  
⏳ 并发访问测试  

## ⚠️ 注意事项

1. **测试环境**: 请在测试环境或本地环境运行,避免污染生产数据
2. **数据清理**: 测试后使用 `test_clear_all_logs.php` 清理日志
3. **权限问题**: Shell脚本可能需要执行权限 `chmod +x`
4. **依赖检查**: 确保PHP版本 >= 8.0,Laravel依赖已安装

## 🐛 问题排查

### 问题1: 权限错误

```bash
# 解决方法
chmod +x test-scripts/*.sh
```

### 问题2: 数据库连接失败

```bash
# 检查 .env 配置
cat .env | grep DB_

# 测试数据库连接
php artisan tinker
>>> DB::connection()->getPdo();
```

### 问题3: 找不到租户

```bash
# 检查租户是否存在
php artisan tinker
>>> App\Models\Tenant::all();

# 创建测试租户
php test-scripts/create-test-tenants.php
```

## 📝 添加新测试

如果需要添加新的测试脚本:

1. 将测试文件放到此目录
2. 更新此 README 的文件列表
3. 添加使用说明
4. 如有特殊依赖,请在"测试准备"中说明

---

**最后更新**: 2024-11-25  
**维护者**: VpnAll Team

