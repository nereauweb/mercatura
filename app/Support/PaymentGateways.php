<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the PaymentGateway for a checkout payment method from
 * config('mercatura.payments.gateways') (method key → driver class).
 * Methods without a gateway (bank_transfer) are handled by the controller.
 */
final class PaymentGateways
{
    public function __construct(private readonly Container $container) {}

    public function has(string $method): bool
    {
        return is_string($this->gateways()[$method] ?? null);
    }

    public function for(string $method): PaymentGateway
    {
        $class = $this->gateways()[$method] ?? null;
        if (! is_string($class)) {
            throw new InvalidArgumentException("No payment gateway configured for [{$method}].");
        }
        $gateway = $this->container->make($class);
        if (! $gateway instanceof PaymentGateway) {
            throw new InvalidArgumentException("[{$class}] does not implement PaymentGateway.");
        }

        return $gateway;
    }

    /**
     * Payment methods offered at checkout, in config order.
     *
     * @return list<string>
     */
    public function enabledMethods(): array
    {
        return array_values(array_filter(array_map('strval', (array) config('mercatura.checkout.payment_methods', []))));
    }

    /**
     * @return array<string, mixed>
     */
    private function gateways(): array
    {
        return (array) config('mercatura.payments.gateways', []);
    }
}
