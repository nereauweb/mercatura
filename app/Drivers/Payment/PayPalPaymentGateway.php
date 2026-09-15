<?php

declare(strict_types=1);

namespace App\Drivers\Payment;

use App\Contracts\PaymentConfirmation;
use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

/** PayPal Orders API (checkout with capture) through srmklive/paypal; credentials in config/paypal.php. */
final class PayPalPaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'paypal';
    }

    public function start(Order $order, Authenticatable $user): string
    {
        FrontendDebugLog::carrelloPagamento('store_order:payment_branch', [
            'branch' => 'paypal',
            'order_id' => $order->id,
            'amount' => round((float) $order->total_taxed_price, 2),
        ]);

        $provider = $this->client();
        FrontendDebugLog::carrelloPagamento('store_order:paypal:token_ok', ['order_id' => $order->id]);

        $response = $provider->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->id.'_'.date('d_m_Y_H_i_s'),
                'amount' => [
                    'currency_code' => $this->currency(),
                    'value' => round((float) $order->total_taxed_price, 2),
                ],
            ]],
            'application_context' => [
                'cancel_url' => route('frontend.payment.paypal.cancel', ['id' => $order->id]),
                'return_url' => route('frontend.payment.paypal.success', ['id' => $order->id]),
            ],
        ]);
        if (! is_array($response)) {
            $response = [];
        }

        // Keep the PayPal order id so confirm() can capture it on return.
        $order->payment_transaction = $response['id'] ?? null;
        $order->save();
        FrontendDebugLog::carrelloPagamento('store_order:paypal:order_created', [
            'order_id' => $order->id,
            'paypal_order_id' => $response['id'] ?? null,
        ]);

        $approveUrl = $this->approvalUrl($response['links'] ?? []);
        if ($approveUrl === null || $approveUrl === '') {
            CaughtExceptionLogger::error('PayPalPaymentGateway::start approve link missing', new RuntimeException('No approve/payer-action link'), [
                'order_id' => $order->id,
            ]);
            throw new RuntimeException('PayPal: approval link not available');
        }

        return $approveUrl;
    }

    public function confirm(Order $order, array $params): PaymentConfirmation
    {
        if ($order->payment_method !== 'paypal' || empty($order->payment_transaction)) {
            FrontendDebugLog::carrelloPagamento('paypal_payment_success:invalid_order_state', [
                'order_id' => $order->id,
                'payment_method' => $order->payment_method,
                'has_transaction' => ! empty($order->payment_transaction),
            ]);

            return PaymentConfirmation::NotPending;
        }

        $provider = $this->client();
        $transaction = (string) $order->payment_transaction;

        $paypalOrder = $provider->capturePaymentOrder($transaction);
        if (! is_array($paypalOrder)) {
            $paypalOrder = [];
        }
        if (isset($paypalOrder['error'])) {
            // Already captured (double return) or transient error: read the order state instead.
            FrontendDebugLog::carrelloPagamento('paypal_payment_success:capture_error_response', [
                'order_id' => $order->id,
                'error' => $paypalOrder['error'],
            ]);
            $paypalOrder = $provider->showOrderDetails($transaction);
            if (! is_array($paypalOrder) || isset($paypalOrder['error'])) {
                return PaymentConfirmation::Unverifiable;
            }
        }

        $status = $paypalOrder['status'] ?? null;
        FrontendDebugLog::carrelloPagamento('paypal_payment_success:capture_response', [
            'order_id' => $order->id,
            'capture_status' => $status,
        ]);

        if ($status !== 'COMPLETED' || ! $this->responseMatchesOrder($paypalOrder, $order)) {
            CaughtExceptionLogger::error('PayPalPaymentGateway::confirm invalid capture state', new RuntimeException('PayPal order not completed or amount mismatch'), [
                'order_id' => $order->id,
                'status' => $status,
            ]);

            return PaymentConfirmation::Mismatch;
        }

        return PaymentConfirmation::Confirmed;
    }

    private function client(): PayPalClient
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials((array) config('paypal'));
        $provider->setAccessToken($provider->getAccessToken());

        return $provider;
    }

    private function currency(): string
    {
        return strtoupper((string) config('paypal.currency', 'EUR'));
    }

    /**
     * @param  array<string, mixed>  $paypalOrder
     */
    private function responseMatchesOrder(array $paypalOrder, Order $order): bool
    {
        if (($paypalOrder['status'] ?? '') !== 'COMPLETED') {
            return false;
        }
        $unit = $paypalOrder['purchase_units'][0] ?? null;
        $amount = is_array($unit) ? ($unit['amount'] ?? null) : null;
        if (! is_array($amount)) {
            return false;
        }
        if (strtoupper((string) ($amount['currency_code'] ?? '')) !== $this->currency()) {
            return false;
        }

        return round((float) $order->total_taxed_price, 2) === round((float) ($amount['value'] ?? 0), 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $links
     */
    private function approvalUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if (in_array($link['rel'] ?? '', ['approve', 'payer-action'], true)) {
                return $link['href'] ?? null;
            }
        }

        return null;
    }
}
