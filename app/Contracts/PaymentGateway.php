<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A hosted payment flow for one checkout method (config
 * mercatura.checkout.payment_methods → mercatura.payments.gateways).
 * Controllers redirect to start() and pass the provider's return to confirm();
 * order status changes stay in App\Services\OrderPaymentCompletion.
 */
interface PaymentGateway
{
    /** Payment method key stored on the order, e.g. "stripe". */
    public function key(): string;

    /**
     * Prepare the hosted payment for a stored order and return the URL the
     * customer is redirected to. May persist a transaction reference on the order.
     */
    public function start(Order $order, Authenticatable $user): string;

    /**
     * Verify the customer's return from the provider.
     *
     * @param  array<string, mixed>  $params  query/input of the return request
     */
    public function confirm(Order $order, array $params): PaymentConfirmation;
}
