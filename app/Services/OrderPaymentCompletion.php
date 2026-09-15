<?php

namespace App\Services;

use App\Models\Order;

/**
 * Provider-neutral order payment helpers shared by the payment gateways
 * (app/Drivers/Payment) and the checkout controllers.
 */
class OrderPaymentCompletion
{
    public static function expectedAmountCents(Order $order): int
    {
        return (int) round((float) $order->total_taxed_price * 100);
    }

    /**
     * Mark order paid and send notifications only on first transition to paid.
     */
    public static function markPaidIfNeeded(Order $order): bool
    {
        if ($order->payment_status === 'paid' && $order->status === 'paid') {
            return false;
        }
        $order->status = 'paid';
        $order->payment_status = 'paid';
        $order->save();
        $order->send_notification('payed', 'user');
        $order->send_notification('payed', 'admin');

        return true;
    }
}
