<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * orders.status. Values are the database enum; labels match the legacy
 * Order::$status_names (kept until the legacy admin is removed).
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Requested = 'requested';
    case PaymentNotified = 'payment_notified';
    case Paid = 'paid';
    case Processing = 'processing';
    case Delivering = 'delivering';
    case Complete = 'complete';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Requested => 'Richiesto',
            self::PaymentNotified => 'Segnalato pagamento',
            self::Paid => 'Pagato',
            self::Processing => 'In preparazione',
            self::Delivering => 'In consegna',
            self::Complete => 'Completato',
            self::Cancelled => 'Cancellato',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested, self::PaymentNotified => 'warning',
            self::Paid, self::Processing => 'info',
            self::Delivering => 'primary',
            self::Complete => 'success',
            self::Cancelled => 'danger',
            self::Draft => 'gray',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $c) => $c->getLabel(), self::cases()));
    }
}
