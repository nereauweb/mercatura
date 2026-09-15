<?php

declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Contracts\PaymentConfirmation;
use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentCompletion;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Throwable;

/**
 * Stripe Checkout through Laravel Cashier (keys in config/cashier.php).
 * The webhook side lives in StripeWebhookListener.
 */
final class StripePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function start(Order $order, Authenticatable $user): string
    {
        if (! $user instanceof User) {
            throw new \InvalidArgumentException('Stripe checkout requires an App\Models\User.');
        }

        FrontendDebugLog::carrelloPagamento('store_order:payment_branch', [
            'branch' => 'stripe',
            'order_id' => $order->id,
            'amount_cents' => OrderPaymentCompletion::expectedAmountCents($order),
        ]);

        $customerCreated = false;
        if (! $user->hasStripeId()) {
            $user->createAsStripeCustomer([
                'metadata' => [
                    'user_id' => $user->id,
                    'registration_date' => $user->created_at?->toDateString(),
                ],
            ]);
            $customerCreated = true;
        }
        FrontendDebugLog::carrelloPagamento('store_order:stripe:customer_ready', [
            'order_id' => $order->id,
            'stripe_customer_created' => $customerCreated,
        ]);

        $checkout = $user->checkoutCharge(
            amount: OrderPaymentCompletion::expectedAmountCents($order),
            name: __('frontend.checkout.payment_line', ['brand' => config('brand.name'), 'id' => $order->id]),
            quantity: 1,
            sessionOptions: [
                'success_url' => $this->successUrl($order),
                'cancel_url' => route('frontend.payment.stripe.cancel', ['id' => $order->id]),
                'expires_at' => now()->addMinutes(30)->timestamp,
                'client_reference_id' => $order->id,
                'metadata' => [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'order_total' => $order->total_taxed_price,
                    'created_at' => now()->toDateTimeString(),
                ],
            ],
        );
        $url = (string) $checkout->asStripeCheckoutSession()->url;
        FrontendDebugLog::carrelloPagamento('store_order:stripe:redirect_checkout', [
            'order_id' => $order->id,
            'has_checkout_url' => $url !== '',
        ]);

        return $url;
    }

    public function confirm(Order $order, array $params): PaymentConfirmation
    {
        $sessionId = $params['session_id'] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            FrontendDebugLog::carrelloPagamento('stripe_payment_success:missing_session_id', ['order_id' => $order->id]);

            return PaymentConfirmation::MissingReference;
        }

        try {
            $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId);
        } catch (Throwable $e) {
            CaughtExceptionLogger::error('StripePaymentGateway::confirm session retrieve failed', $e, ['order_id' => $order->id]);

            return PaymentConfirmation::Unverifiable;
        }

        if (! $this->sessionMatchesOrder($session, $order)) {
            CaughtExceptionLogger::error('StripePaymentGateway::confirm session mismatch', new \RuntimeException('Stripe session does not match order'), [
                'order_id' => $order->id,
                'session_id' => $sessionId,
            ]);

            return PaymentConfirmation::Mismatch;
        }

        return PaymentConfirmation::Confirmed;
    }

    /** Success URL carrying the Checkout session id back to confirm(). */
    private function successUrl(Order $order): string
    {
        $base = route('frontend.payment.stripe.success', ['id' => $order->id]);

        return $base.(str_contains($base, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
    }

    private function sessionMatchesOrder(StripeCheckoutSession $session, Order $order): bool
    {
        if ($order->payment_method !== 'stripe') {
            return false;
        }
        if (($session->payment_status ?? '') !== 'paid') {
            return false;
        }
        if ((int) ($session->amount_total ?? 0) !== OrderPaymentCompletion::expectedAmountCents($order)) {
            return false;
        }
        if (strtolower((string) ($session->currency ?? '')) !== strtolower((string) config('cashier.currency', 'eur'))) {
            return false;
        }
        if ((string) ($session->client_reference_id ?? '') !== (string) $order->id) {
            return false;
        }

        return (string) ($session->metadata['order_id'] ?? null) === (string) $order->id;
    }
}
