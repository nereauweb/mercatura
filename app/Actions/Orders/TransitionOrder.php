<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

/**
 * The order status machine of the legacy admin (AdminOrdersController::update),
 * reproduced exactly (docs/02_V2B_ADMIN.md §4.3):
 * - payment_method changes only while the order is draft or requested;
 * - payment_status becoming "paid" promotes draft/requested to "paid" and
 *   mails order_paid_user; the status change in the same save sends no other mail;
 * - a changed tracking code mails order_sent;
 * - otherwise a status change mails order_cancelled or order_updated.
 */
final class TransitionOrder
{
    /**
     * @param  array<string, string|\BackedEnum|null>  $input  status, payment_status, payment_method, tracking_code
     * @return list<string> notifications sent, e.g. ['payed', 'sent']
     */
    public function handle(Order $order, array $input): array
    {
        // Enum-backed selects may hand over enum cases; the columns are strings until v2b.6.
        $input = array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $input);

        $editableMethod = in_array($order->status, [OrderStatus::Draft->value, OrderStatus::Requested->value], true);
        if ($editableMethod && array_key_exists('payment_method', $input) && $input['payment_method'] !== null) {
            $order->payment_method = $input['payment_method'];
        }

        $originalStatus = $order->status;
        if (array_key_exists('status', $input) && $input['status'] !== null) {
            $order->status = $input['status'];
        }

        $originalPaymentStatus = $order->payment_status;
        if (array_key_exists('payment_status', $input) && $input['payment_status'] !== null) {
            $order->payment_status = $input['payment_status'];
        }

        $originalTracking = $order->tracking_code;
        if (array_key_exists('tracking_code', $input)) {
            $order->tracking_code = $input['tracking_code'] !== null && trim((string) $input['tracking_code']) !== '' ? trim((string) $input['tracking_code']) : null;
        }

        $order->save();

        $sent = [];
        $genericUpdate = true;
        if ($order->payment_status !== $originalPaymentStatus && $order->payment_status === PaymentStatus::Paid->value) {
            if (in_array($order->status, [OrderStatus::Draft->value, OrderStatus::Requested->value], true)) {
                $order->status = OrderStatus::Paid->value;
                $order->save();
            }
            $order->send_notification('payed', 'user');
            $sent[] = 'payed';
            $genericUpdate = false;
        }
        if ((string) $order->tracking_code !== (string) $originalTracking) {
            $order->send_notification('sent', 'user');
            $sent[] = 'sent';
            $genericUpdate = false;
        }
        if ($genericUpdate && $order->status !== $originalStatus) {
            $action = $order->status === OrderStatus::Cancelled->value ? 'cancelled' : 'updated';
            $order->send_notification($action, 'user');
            $sent[] = $action;
        }

        return $sent;
    }
}
