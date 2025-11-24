# XBoard 部署脚本说明

## 📦 快速部署

### 完整部署（推荐）
```bash
cd /home/martnlei/codeSpace/XboradAll/VpnAll/xboard
./scripts/deploy.sh
```

**功能**:
- ✅ 同步所有代码文件到Docker容器
- ✅ 同步数据库迁移文件
- ✅ 执行数据库迁移
- ✅ 清除所有缓存（config/route/view/cache）
- ✅ 验证部署文件和表结构
- ✅ 测试API端点和数据隔离

## 🔧 问题排查

### 1. 容器状态检查
```bash
docker ps | grep xboard
# 确保容器状态为 Up (healthy)
```

### 2. 查看容器日志
```bash
docker logs -f xboard-app-test
```

### 3. 进入容器调试
```bash
docker exec -it xboard-app-test sh
cd /var/www/html
php artisan tinker
```

### 4. 手动执行迁移
```bash
docker exec xboard-app-test php artisan migrate --force
```

### 5. 清除缓存
```bash
docker exec xboard-app-test php artisan cache:clear
docker exec xboard-app-test php artisan config:clear
docker exec xboard-app-test php artisan route:clear
```

## 📊 验证数据隔离

### 测试套餐数据
```bash
docker exec xboard-app-test php artisan tinker --execute="
echo '所有套餐：' . Plan::withoutGlobalScope(App\Scopes\TenantScope::class)->count() . PHP_EOL;
echo '租户13的套餐：' . Plan::where('tenant_id', 13)->count() . PHP_EOL;
"
```

### 测试API端点
```bash
# 获取token
TOKEN=$(docker exec xboard-app-test php artisan tinker --execute="echo User::where('email', 'admin@vpnall.com')->first()->token;")

# 测试租户13的套餐
curl -H "Authorization: Bearer $TOKEN" \
     -H "X-Super-Admin: true" \
     -H "X-Tenant-ID: 13" \
     http://localhost:8080/api/v2/admin/plan/fetch
```

## 🚀 前端部署

### 重启前端开发服务器
```bash
cd /home/martnlei/codeSpace/XboradAll/VpnAll/xboard-admin-source
pkill -f "vite"
npm run dev
```

### 访问管理后台
```
URL: http://localhost:3000/#/plan-management
账号: admin@vpnall.com
密码: Admin2025
```

## 📝 关键文件

### 后端
- `app/Models/Plan.php` - Plan模型（包含BelongsToTenant trait）
- `app/Http/Controllers/V2/Admin/PlanController.php` - 套餐控制器
- `database/migrations/2025_01_24_000001_add_tags_to_plan.php` - tags字段迁移

### 前端
- `src/pages/plan-management/index.tsx` - 套餐管理页面
- `src/components/TenantSelector.tsx` - 站点选择器
- `src/services/api/admin.ts` - API服务

## ⚠️ 常见问题

### Q: 部署后API返回500
A: 
1. 检查容器健康状态
2. 查看PHP-FPM错误日志
3. 确认所有迁移已执行
4. 清除Laravel缓存

### Q: 前端无法显示数据
A:
1. 清除浏览器缓存（Ctrl+Shift+R）
2. 检查浏览器控制台错误
3. 验证API响应格式
4. 重启Vite开发服务器

### Q: Docker容器unhealthy
A:
1. 重启容器：`docker restart xboard-app-test`
2. 查看健康检查日志
3. 验证数据库连接
4. 检查nginx/php-fpm配置

## 📞 技术支持

遇到问题请提供：
1. 部署脚本输出
2. 容器日志（最后100行）
3. API响应内容
4. 浏览器控制台错误

