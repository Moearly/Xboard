# VpnAll 快速部署指南

本指南帮助您快速部署 VpnAll 多租户 VPN 面板系统。

## 🎯 部署选择

根据您的需求选择合适的部署方式：

### 场景1：测试/演示环境 (推荐新手)
**使用IP直接访问部署**
```bash
cd scripts/deployment
chmod +x ip-deploy.sh
./ip-deploy.sh
```
- ✅ 无需域名配置
- ✅ 自动配置防火墙
- ✅ 包含完整监控
- ✅ 适合快速体验

### 场景2：生产环境
**使用域名部署**
```bash
cd scripts/deployment
chmod +x remote-deploy.sh
./remote-deploy.sh
```
- ✅ 支持自定义域名
- ✅ 包含SSL配置
- ✅ 完整多租户功能
- ✅ 生产级部署

### 场景3：开发环境
**完整开发部署**
```bash
cd scripts/deployment
chmod +x complete-deploy.sh
./complete-deploy.sh
```
- ✅ 前端热重载
- ✅ 开发者工具
- ✅ 调试功能完整
- ✅ 代码实时更新

## 📋 系统要求

### 最低配置
- **CPU**: 1核心
- **内存**: 2GB RAM
- **存储**: 20GB SSD
- **网络**: 10Mbps 带宽

### 推荐配置
- **CPU**: 2核心+
- **内存**: 4GB+ RAM
- **存储**: 50GB+ SSD
- **网络**: 100Mbps+ 带宽

### 软件要求
- Ubuntu 20.04+ / CentOS 8+ / Debian 11+
- Docker 20.10+
- Docker Compose 2.0+

## 🚀 一键部署流程

### 步骤1: 准备服务器
```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装基础工具
sudo apt install -y curl wget git
```

### 步骤2: 克隆项目
```bash
git clone https://github.com/yourusername/VpnAll.git
cd VpnAll
```

### 步骤3: 选择部署脚本
```bash
# 新手推荐 - IP部署
./scripts/deployment/ip-deploy.sh

# 或生产环境 - 域名部署
./scripts/deployment/remote-deploy.sh
```

### 步骤4: 验证部署
```bash
# 检查服务状态
./scripts/management/check-deployment.sh

# 测试API接口
./scripts/testing/check-xboard-api.sh
```

## 🔧 部署后配置

### 1. 首次登录
部署完成后，使用以下信息登录：
- **邮箱**: `admin@vpnall.com`
- **密码**: 脚本生成的随机密码（会显示在部署完成信息中）

### 2. 修改管理员密码
```bash
# SSH登录服务器
ssh root@your_server_ip

# 进入项目目录
cd /opt/vpnall

# 重置密码
docker-compose exec xboard php artisan tinker
>>> $user = App\Models\User::where('email', 'admin@vpnall.com')->first();
>>> $user->password = Hash::make('your_new_password');
>>> $user->save();
```

### 3. 配置域名 (可选)
如果有域名，按以下步骤配置：
1. 将域名A记录指向服务器IP
2. 等待DNS生效
3. 配置SSL证书（推荐使用Let's Encrypt）

### 4. 添加节点服务器
1. 登录管理后台
2. 进入"节点管理"
3. 添加您的节点服务器信息
4. 配置节点通信密钥

## 🛡 安全配置

### 1. 防火墙配置
脚本会自动配置以下端口：
```bash
# Web服务
80/tcp   - HTTP
443/tcp  - HTTPS

# VPN协议端口
8080/tcp  - V2Ray HTTP
8443/tcp  - V2Ray HTTPS
2053/tcp  - VMess
2083/tcp  - VLESS
2087/tcp  - Trojan
2096/tcp  - Shadowsocks

# 管理端口
22/tcp    - SSH (限制访问)
```

### 2. 强化安全
```bash
# 修改SSH端口
sudo nano /etc/ssh/sshd_config
# Port 22 改为其他端口

# 禁用密码登录，使用密钥
# PasswordAuthentication no

# 重启SSH服务
sudo systemctl restart sshd
```

### 3. 启用SSL (生产环境必须)
```bash
# 安装Certbot
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d yourdomain.com

# 自动续期
sudo crontab -e
# 添加: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 📊 监控和维护

### 1. 查看服务状态
```bash
# 查看所有容器
docker-compose ps

# 查看特定服务日志
docker-compose logs -f xboard
docker-compose logs -f nginx
docker-compose logs -f mysql
```

### 2. 性能监控
```bash
# 查看资源使用
docker stats

# 查看磁盘使用
df -h

# 查看内存使用
free -h
```

### 3. 数据备份
```bash
# 备份数据库
docker-compose exec mysql mysqldump -u root -p xboard > backup.sql

# 备份配置文件
tar -czf config_backup.tar.gz .env docker-compose.yml nginx/
```

## 🔍 故障排除

### 常见问题1: 容器启动失败
```bash
# 查看具体错误
docker-compose logs

# 重新构建容器
docker-compose down
docker-compose up -d --build
```

### 常见问题2: 无法访问网站
```bash
# 检查端口监听
netstat -tlnp | grep :80

# 检查防火墙
ufw status

# 检查Nginx配置
nginx -t
```

### 常见问题3: 数据库连接失败
```bash
# 检查数据库状态
docker-compose exec mysql mysql -u root -p -e "SHOW DATABASES;"

# 重启数据库
docker-compose restart mysql
```

## 📞 获取支持

### 查看部署信息
部署完成后，所有重要信息都会保存在 `deployment_info.txt` 文件中。

### 技术支持
- **文档**: 查看 [项目文档](../README.md)
- **脚本问题**: 查看 [脚本说明](../../scripts/README.md)
- **API问题**: 使用测试脚本诊断
- **系统问题**: 查看日志文件

---

**部署有问题？** 请先运行诊断脚本：
```bash
./scripts/management/check-deployment.sh
./scripts/testing/check-xboard-api.sh
```

然后将结果提交到项目Issue页面。
