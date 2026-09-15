<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** orders.payment_method; the same keys as config mercatura.checkout.payment_methods. */
enum PaymentMethod: string implements HasLabel
{
    case BankTransfer = 'bank_transfer';
    case Stripe = 'stripe';
    case PayPal = 'paypal';

    public function getLabel(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bonifico',
            self::Stripe => 'Stripe',
            self::PayPal => 'Paypal',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->getLabel(), self::cases()));
    }
}
