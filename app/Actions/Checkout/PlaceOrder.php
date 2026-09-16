<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Orders\StoreOrderItemCustomizations;
use App\Models\Order;
use App\Models\User;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;
use App\Support\PaymentGateways;
use Illuminate\Contracts\Session\Session;
use RuntimeException;

/**
 * Turns the priced cart (CartData::build) into an order with its items,
 * articles and customization snapshot, then completes it: notifications,
 * cart cleared for a bank transfer, hosted payment started for a gateway.
 * Shared by the steps and the onepage checkout.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly StoreOrderItemCustomizations $customizations,
        private readonly PaymentGateways $gateways,
    ) {}

    /**
     * @param  array<string, mixed>  $cart
     * @param  array<int, string>  $artworkFiles  option id => absolute path
     */
    public function handle(User $user, array $cart, string $paymentMethod, array $artworkFiles): Order
    {
        $customer = $user->customer;
        $order = Order::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'items_price' => $cart['items_price'],
            'delivery_cost' => $cart['delivery_cost'],
            'total_price' => $cart['total_price'],
            'total_tax' => $cart['tax'],
            'total_taxed_price' => $cart['total_taxed_price'],
            'address' => $customer->shipping_address->address,
            'province' => $customer->shipping_address->province,
            'city' => $customer->shipping_address->city,
            'zip_code' => $customer->shipping_address->zip_code,
            'country' => $customer->shipping_address->country,
            'notes' => $customer->shipping_address->notes,
            'status' => 'requested',
            'payment_method' => $paymentMethod,
            'payment_status' => 'unpaid',
        ]);
        FrontendDebugLog::carrelloPagamento('store_order:order_persisted', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'items_lines' => count($cart['items'] ?? []),
            'total_taxed_price' => $order->total_taxed_price,
            'payment_status' => $order->payment_status,
        ]);
        foreach ($cart['items'] as $item) {
            $orderItem = $order->items()->create([
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'unit_price' => $item['unit_price'],
                'is_sample' => $item['sample'] ? 1 : 0,
                'shipping_date' => $item['shipping_date']?->toDateString(),
                'product_id' => $item['product']->id,
                'product_sku' => $item['product']->sku,
                'product_name' => $item['product']->name,
                'product_image_url' => $item['product']->cover(true),
            ]);
            foreach ($item['articles'] as $article) {
                $orderItem->articles()->create([
                    'article_id' => $article['article']->id,
                    'article_sku' => $article['article']->sku,
                    'article_size_label' => $article['article']->size->label,
                    'article_color_label' => $article['article']->color->label,
                    'article_image_url' => $article['article']->cover(true),
                    'quantity' => $article['quantity'],
                    'unit_price' => $article['unit_price'],
                    'price' => $article['quantity_price'],
                ]);
            }
            $this->customizations->handle($orderItem, $item['line'], $artworkFiles);
        }

        return $order;
    }

    /**
     * Notifications and the payment branch. Returns the hosted payment URL for
     * a gateway, null for a bank transfer (cart cleared); throws for a method
     * that is configured but has no gateway.
     */
    public function complete(Order $order, User $user, Session $session): ?string
    {
        if ($order->payment_method === 'bank_transfer') {
            FrontendDebugLog::carrelloPagamento('store_order:payment_branch', ['branch' => 'bank_transfer', 'order_id' => $order->id]);
            $this->notify($order);
            $session->put('cart', []);
            FrontendDebugLog::carrelloPagamento('store_order:bank_transfer:completed', ['order_id' => $order->id, 'cart_cleared' => true]);

            return null;
        }
        if ($this->gateways->has($order->payment_method)) {
            $this->notify($order);
            try {
                // The gateway (config mercatura.payments.gateways) prepares the hosted payment.
                return $this->gateways->for($order->payment_method)->start($order, $user);
            } catch (\Throwable $e) {
                CaughtExceptionLogger::error('PlaceOrder payment start failed', $e, ['order_id' => $order->id, 'payment_method' => $order->payment_method]);
                FrontendDebugLog::carrelloPagamento('store_order:payment:error', ['order_id' => $order->id, 'payment_method' => $order->payment_method, 'error' => $e->getMessage()]);
                throw $e;
            }
        }
        FrontendDebugLog::carrelloPagamento('store_order:unsupported_payment_method', ['order_id' => $order->id, 'payment_method' => $order->payment_method]);

        throw new RuntimeException('Unsupported payment method '.$order->payment_method);
    }

    /**
     * The "stored" notifications. A mail failure (provider down, template
     * missing) is logged and never undoes an order that is already persisted:
     * the admin sees the order, the customer sees the outcome page.
     */
    private function notify(Order $order): void
    {
        foreach (['user', 'admin'] as $to) {
            try {
                $order->send_notification('stored', $to);
            } catch (\Throwable $e) {
                CaughtExceptionLogger::error('PlaceOrder notification failed', $e, ['order_id' => $order->id, 'to' => $to]);
            }
        }
    }
}
