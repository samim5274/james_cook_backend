<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

class PointService
{
    public function salePoint($customer, $orderReg, $orderPoint)
    {
        if (!$customer || $orderPoint <= 0) {
            return null;
        }

        $order = Order::query()
            ->where('reg', $orderReg)
            ->first();

        if (!$order) {
            throw new \Exception("Order not found: {$orderReg}");
        }

        $exists = PointTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', 'earn')
            ->exists();

        if ($exists) {
            return null;
        }

        return PointTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'earn',
            'points' => $orderPoint,
            'status' => 'credit',
            'source' => 'order',
            'order_id' => $order->id,
            'remarks' => "Points earned from order {$orderReg}",
        ]);
    }

    public function redeemPoint($customer, $order, $points)
    {
        if (!$customer || $points <= 0) {
            throw new \Exception('Invalid point amount.');
        }

        $customer = $customer->newQuery()
            ->whereKey($customer->id)
            ->lockForUpdate()
            ->firstOrFail();

        $exists = PointTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', 'redeem')
            ->exists();

        if ($exists) {
            return null;
        }

        $balance = $this->getBalance($customer->id);

        if ($balance < $points) {
            throw new \Exception(
                "Insufficient points. Available: {$balance}, Required: {$points}"
            );
        }

        return PointTransaction::create([
            'customer_id' => $customer->id,
            'type' => 'redeem',
            'points' => $points,
            'status' => 'debit',
            'source' => 'order',
            'order_id' => $order->id,
            'remarks' => "Points redeemed for order {$order->reg}",
        ]);
    }

    public function getBalance($customerId)
    {
        return (int) PointTransaction::query()
            ->where('customer_id', $customerId)
            ->selectRaw("
                COALESCE(
                    SUM(
                        CASE
                            WHEN status = 'credit' THEN points
                            WHEN status = 'debit' THEN -points
                            ELSE 0
                        END
                    ), 0
                ) AS balance
            ")
            ->value('balance');
    }
}