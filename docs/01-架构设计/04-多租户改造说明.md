# 基于 Xboard 实际代码的多租户改造方案

## 一、Xboard 项目结构分析

Xboard 是一个基于 Laravel 的 V2Ray 面板系统，主要模型包括：
- `User.php` - 用户模型
- `Order.php` - 订单模型  
- `Plan.php` - 套餐模型
- `Server.php` - 服务器节点模型
- `Payment.php` - 支付模型

## 二、具体改造步骤（基于 Xboard 实际代码）

### 步骤 1：复制 Xboard 项目并创建改造分支

```bash
# 复制 Xboard 到我们的项目
cp -r Xboard-Original/* /Users/martnlei/Code/Work/VpnAll/
cd /Users/martnlei/Code/Work/VpnAll/

# 初始化 git（如果需要）
git init
git add .
git commit -m "Initial Xboard base"
git checkout -b multi-tenant-feature
```

### 步骤 2：修改 Xboard 的 User 模型

**原始文件：`app/Models/User.php`**

```php
<?php

namespace App\Models;

use App\Utils\Helper;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
// ... 其他导入

class User extends Authenticatable
{
    // Xboard 原有代码...
}
```

**改造后：添加租户支持**

```php
<?php

namespace App\Models;

use App\Utils\Helper;
use App\Models\Traits\BelongsToTenant; // [新增] 导入租户 trait
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
// ... 其他导入保持不变

class User extends Authenticatable
{
    use HasApiTokens, BelongsToTenant; // [新增] 添加 BelongsToTenant
    
    // Xboard 原有的所有属性和方法完全保持不变
    // 例如：
    protected $fillable = [
        'email',
        'password',
        'token',
        'uuid',
        'plan_id',
        // ... Xboard 原有字段
        'tenant_id', // [新增] 只添加这一个字段
    ];
    
    // Xboard 原有的关系方法保持不变
    public function invite_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invite_user_id');
    }
    
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
    
    // ... 所有 Xboard 原有方法保持不变
}
```

### 步骤 3：修改 Xboard 的 Order 模型

**原始文件：`app/Models/Order.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // Xboard 原有属性
    protected $fillable = [
        'user_id',
        'plan_id',
        'payment_id',
        'period',
        'trade_no',
        // ... 其他字段
    ];
}
```

**改造后：**

```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant; // [新增]
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant; // [新增] 添加租户 trait
    
    protected $fillable = [
        'user_id',
        'plan_id',
        'payment_id',
        'period',
        'trade_no',
        // ... Xboard 原有字段保持不变
        'tenant_id', // [新增] 只添加租户ID
    ];
    
    // Xboard 所有原有方法保持不变
}
```

### 步骤 4：修改 Xboard 的 Plan 模型

**原始文件：`app/Models/Plan.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'group_id',
        'transfer_enable',
        'speed_limit',
        // ... 其他字段
    ];
}
```

**改造后：**

```php
<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant; // [新增]
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use BelongsToTenant; // [新增]
    
    protected $fillable = [
        'name',
        'group_id',
        'transfer_enable',
        'speed_limit',
        // ... Xboard 原有字段
        'tenant_id', // [新增]
    ];
    
    // Xboard 所有原有方法保持不变
}
```

### 步骤 5：在 Xboard 项目中添加租户支持文件

在 Xboard 项目结构中添加以下文件：

```
Xboard/
├── app/
│   ├── Models/
│   │   ├── User.php          # [修改] 添加 BelongsToTenant
│   │   ├── Order.php         # [修改] 添加 BelongsToTenant  
│   │   ├── Plan.php          # [修改] 添加 BelongsToTenant
│   │   ├── Tenant.php        # [新增] 租户模型
│   │   └── Traits/
│   │       └── BelongsToTenant.php # [新增] 租户 trait
│   └── Http/
│       └── Middleware/
│           ├── ... (Xboard 原有中间件)
│           └── TenantIdentification.php # [新增]
└── database/
    └── migrations/
        ├── ... (Xboard 原有迁移)
        ├── 2024_01_01_create_tenants_table.php # [新增]
        └── 2024_01_01_add_tenant_id_to_tables.php # [新增]
```

### 步骤 6：修改 Xboard 的数据库迁移

创建迁移文件，为 Xboard 现有表添加 `tenant_id`：

```php
// database/migrations/2024_01_01_add_tenant_id_to_xboard_tables.php

public function up()
{
    // 为 Xboard 的 users 表添加租户ID
    Schema::table('users', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        $table->index('tenant_id');
    });
    
    // 为 Xboard 的 orders 表添加租户ID
    Schema::table('orders', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        $table->index('tenant_id');
    });
    
    // 为 Xboard 的 plans 表添加租户ID
    Schema::table('plans', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        $table->index('tenant_id');
    });
    
    // 为其他 Xboard 表添加租户ID
    // payments, tickets, announcements 等
}
```

### 步骤 7：修改 Xboard 的路由文件

**原始 `routes/web.php`（Xboard）：**

```php
<?php

use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\User\OrderController;
// ... 其他控制器

Route::prefix('api')->group(function () {
    // Xboard 原有路由
    Route::prefix('v1')->group(function () {
        // 用户端
        Route::prefix('user')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            // ... 其他路由
        });
    });
});
```

**改造后：添加租户路由**

```php
<?php

use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\User\OrderController;
use App\Http\Controllers\Admin\TenantController; // [新增]
// ... 其他控制器

// [新增] 超级管理员路由（不应用租户中间件）
Route::domain(config('app.admin_domain', 'admin.vpnall.com'))->group(function () {
    Route::prefix('api/admin')->group(function () {
        Route::resource('tenants', TenantController::class);
    });
});

// [修改] 原有 Xboard 路由，添加租户中间件
Route::prefix('api')->middleware(['tenant'])->group(function () {
    // Xboard 所有原有路由保持不变，只是自动应用了租户过滤
    Route::prefix('v1')->group(function () {
        Route::prefix('user')->group(function () {
            Route::post('/login', [AuthController::class, 'login']);
            // ... 其他路由完全不变
        });
    });
});
```

### 步骤 8：修改 Xboard 的控制器（最小改动）

Xboard 的控制器基本不需要改动！因为租户过滤是通过 Model 的全局作用域自动实现的。

例如，Xboard 原有的用户控制器：

```php
// app/Http/Controllers/V1/User/UserController.php
class UserController extends Controller
{
    public function index()
    {
        // Xboard 原代码
        $users = User::all(); 
        // 改造后自动只返回当前租户的用户，无需修改代码！
        
        return response()->json($users);
    }
}
```

## 三、改造效果演示

### 1. 访问租户 A（tenant-a.com）
```
用户登录 → 看到租户 A 的数据
- 用户列表：只有租户 A 的用户
- 套餐列表：只有租户 A 的套餐
- 订单列表：只有租户 A 的订单
- 节点列表：共享的节点（但可自定义显示）
```

### 2. 访问租户 B（tenant-b.com）
```
用户登录 → 看到租户 B 的数据
- 完全独立的用户体系
- 独立的套餐和订单
- 相同的节点池
```

### 3. 访问超级管理后台（admin.vpnall.com）
```
超级管理员登录 → 管理所有租户
- 创建/编辑/删除租户
- 分配节点给租户
- 查看所有租户统计
```

## 四、改造总结

### 改动最小化原则：
1. **保留 99% Xboard 代码**：只添加必要的租户支持
2. **不破坏原有功能**：所有 Xboard 功能继续正常工作
3. **透明的租户过滤**：通过 trait 和中间件自动处理

### 核心改动：
- **模型**：添加 `use BelongsToTenant` trait
- **数据库**：添加 `tenant_id` 字段
- **中间件**：添加租户识别中间件
- **路由**：添加超级管理路由

### 不需要改动的部分：
- ✅ Xboard 的业务逻辑
- ✅ Xboard 的控制器方法
- ✅ Xboard 的服务类
- ✅ Xboard 的前端代码（除了主题系统）
- ✅ Xboard 的 API 接口

这样的改造保持了 Xboard 的完整性，同时添加了多租户功能！