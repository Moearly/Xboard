<?php

namespace App\Http\Controllers\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\Plugin\HookManager;

class PaymentController extends Controller
{
    public function notify($method, $uuid, Request $request)
    {
        Log::info('Payment notify received', [
            'method' => $method,
            'uuid' => $uuid,
            'params' => $request->input(),
            'ip' => $request->ip(),
        ]);
        HookManager::call('payment.notify.before', [$method, $uuid, $request]);
        try {
            $paymentService = new PaymentService($method, null, $uuid);
            $verify = $paymentService->notify($request->input());
            if (!$verify) {
                Log::warning('Payment notify verify failed', ['method' => $method, 'uuid' => $uuid]);
                HookManager::call('payment.notify.failed', [$method, $uuid, $request]);
                return $this->fail([422, 'verify error']);
            }
            Log::info('Payment notify verified', ['trade_no' => $verify['trade_no'], 'callback_no' => $verify['callback_no']]);
            HookManager::call('payment.notify.verified', $verify);
            if (!$this->handle($verify['trade_no'], $verify['callback_no'])) {
                Log::error('Payment handle failed', ['trade_no' => $verify['trade_no']]);
                return $this->fail([400, 'handle error']);
            }
            Log::info('Payment notify success', ['trade_no' => $verify['trade_no']]);
            return (isset($verify['custom_result']) ? $verify['custom_result'] : 'success');
        } catch (\Exception $e) {
            Log::error('Payment notify exception', ['method' => $method, 'uuid' => $uuid, 'error' => $e->getMessage()]);
            return $this->fail([500, 'fail']);
        }
    }

    private function handle($tradeNo, $callbackNo)
    {
        $order = Order::withoutGlobalScopes()->where('trade_no', $tradeNo)->first();
        if (!$order) {
            Log::error('Payment handle: order not found', ['trade_no' => $tradeNo]);
            return $this->fail([400202, 'order is not found']);
        }
        if ($order->status !== Order::STATUS_PENDING)
            return true;
        $orderService = new OrderService($order);
        if (!$orderService->paid($callbackNo)) {
            return false;
        }

        HookManager::call('payment.notify.success', $order);
        return true;
    }
}
