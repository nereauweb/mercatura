<?php

declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Models\Order;
use App\Services\OrderPaymentCompletion;
use Laravel\Cashier\Events\WebhookReceived;

final class StripeWebhookListener
{
    /**
     * Handle received Stripe webhooks.
     */
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if ($type === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event->payload['data']['object'] ?? []);

            return;
        }

        if ($type === 'payment_intent.succeeded') {
            $this->handlePaymentIntentSucceeded($event->payload['data']['object'] ?? []);
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        if (($session['mode'] ?? '') !== 'payment') {
            return;
        }
        if (($session['payment_status'] ?? '') !== 'paid') {
            return;
        }

        $metadata = $session['metadata'] ?? [];
        $orderId = $session['client_reference_id'] ?? $metadata['order_id'] ?? null;
        if ($orderId === null || $orderId === '') {
            return;
        }

        $order = Order::find($orderId);
        if (! $order || $order->payment_method !== 'stripe') {
            return;
        }

        $amountTotal = (int) ($session['amount_total'] ?? 0);
        if ($amountTotal !== OrderPaymentCompletion::expectedAmountCents($order)) {
            return;
        }

        $currency = strtolower((string) ($session['currency'] ?? ''));
        if ($currency !== strtolower(config('cashier.currency', 'eur'))) {
            return;
        }

        if ((string) ($session['client_reference_id'] ?? '') !== (string) $order->id) {
            return;
        }
        if ((string) ($metadata['order_id'] ?? '') !== (string) $order->id) {
            return;
        }

        OrderPaymentCompletion::markPaidIfNeeded($order);
    }

    /**
     * @param  array<string, mixed>  $pi
     */
    private function handlePaymentIntentSucceeded(array $pi): void
    {
        $piMetadata = $pi['metadata'] ?? [];
        $orderId = $piMetadata['order_id'] ?? null;
        if ($orderId === null || $orderId === '') {
            return;
        }

        $order = Order::find($orderId);
        if (! $order || $order->payment_method !== 'stripe') {
            return;
        }

        $amount = (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0);
        if ($amount !== OrderPaymentCompletion::expectedAmountCents($order)) {
            return;
        }

        $currency = strtolower((string) ($pi['currency'] ?? ''));
        if ($currency !== strtolower(config('cashier.currency', 'eur'))) {
            return;
        }

        OrderPaymentCompletion::markPaidIfNeeded($order);
    }
}
