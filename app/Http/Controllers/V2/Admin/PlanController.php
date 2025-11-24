<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanSave;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function fetch(Request $request)
    {
        $plans = Plan::orderBy('sort', 'ASC')
            ->with([
                'group:id,name'
            ])
            ->withCount([
                'users',
                'users as active_users_count' => function ($query) {
                    $query->where(function ($q) {
                        $q->where('expired_at', '>', time())
                          ->orWhereNull('expired_at');
                    });
                }
            ])
            ->get();

        return $this->success($plans);
    }

    public function save(PlanSave $request)
    {
        $params = $request->validated();
        
        // 记录调试信息
        $currentTenant = app()->has('currentTenant') ? app('currentTenant') : null;
        Log::info('Plan save request', [
            'params' => $params,
            'currentTenant' => $currentTenant ? $currentTenant->id : null,
            'user_id' => auth()->id(),
        ]);
        
        if ($request->input('id')) {
            $plan = Plan::find($request->input('id'));
            if (!$plan) {
                return $this->fail([400202, '该订阅不存在']);
            }
            
            DB::beginTransaction();
            try {
                if ($request->input('force_update')) {
                    User::where('plan_id', $plan->id)->update([
                        'group_id' => $params['group_id'],
                        'transfer_enable' => $params['transfer_enable'] * 1073741824,
                        'speed_limit' => $params['speed_limit'],
                        'device_limit' => $params['device_limit'],
                    ]);
                }
                $plan->update($params);
                DB::commit();
                return $this->success(true);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Plan update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return $this->fail([500, '保存失败: ' . $e->getMessage()]);
            }
        }
        
        DB::beginTransaction();
        try {
            $plan = Plan::create($params);
            if (!$plan) {
                throw new \Exception('创建套餐失败');
            }
            DB::commit();
            Log::info('Plan created successfully', ['plan_id' => $plan->id, 'tenant_id' => $plan->tenant_id]);
            return $this->success(true);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Plan create failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return $this->fail([500, '创建失败: ' . $e->getMessage()]);
        }
    }

    public function drop(Request $request)
    {
        // 注意：超级管理员删除时需要禁用租户过滤，否则无法删除其他租户或NULL tenant_id的套餐
        $plan = Plan::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->find($request->input('id'));
            
        if (!$plan) {
            return $this->fail([400202, '该订阅不存在']);
        }
        
        if (Order::where('plan_id', $request->input('id'))->first()) {
            return $this->fail([400201, '该订阅下存在订单无法删除']);
        }
        if (User::where('plan_id', $request->input('id'))->first()) {
            return $this->fail([400201, '该订阅下存在用户无法删除']);
        }
        
        return $this->success($plan->delete());
    }

    public function update(Request $request)
    {
        $updateData = $request->only([
            'show',
            'renew',
            'sell'
        ]);

        $plan = Plan::find($request->input('id'));
        if (!$plan) {
            return $this->fail([400202, '该订阅不存在']);
        }

        try {
            $plan->update($updateData);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '保存失败']);
        }

        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $params = $request->validate([
            'ids' => 'required|array'
        ]);

        try {
            DB::beginTransaction();
            foreach ($params['ids'] as $k => $v) {
                if (!Plan::find($v)->update(['sort' => $k + 1])) {
                    throw new \Exception();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->fail([500, '保存失败']);
        }
        return $this->success(true);
    }
}
