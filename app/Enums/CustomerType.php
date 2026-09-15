<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * customers.customer_type. The stored values are the Italian labels
 * (a data migration on every installation would be needed to change
 * them, see docs/02_V2B_ADMIN.md §7), so value and label coincide.
 */
enum CustomerType: string implements HasLabel
{
    case Company = 'Azienda';
    case Association = 'Associazione / Comitato';
    case PublicAdministration = 'Pubblica amministrazione';
    case Union = 'Sindacato';
    case Private = 'Privato';

    public function getLabel(): string
    {
        return $this->value;
    }

    /** Fiscal fields the storefront and the admin show for this type. */
    public function requiresVat(): bool
    {
        return $this !== self::Private;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_column(self::cases(), 'value'));
    }
}
