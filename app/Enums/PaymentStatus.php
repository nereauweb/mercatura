<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** orders.payment_status. */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Non pagato',
            self::Paid => 'Pagato',
            self::Refunded => 'Rimborsato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Refunded => 'gray',
            self::Unpaid => 'warning',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->getLabel(), self::cases()));
    }
}
