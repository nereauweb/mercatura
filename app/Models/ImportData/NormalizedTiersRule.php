<?php

declare(strict_types=1);

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;

/**
 * Quantity breaks generated at import for products whose supplier gives no
 * tiers: for a unit cost between from_price and to_price, the four
 * quantities at which a price list row is written (MarkupRules::tiers).
 * Edited in the admin (Sistema → Regole di prezzo: scaglioni).
 */
class NormalizedTiersRule extends Model
{
    /** MarkupRules keeps these rows in memory: drop them when one changes. */
    protected static function booted(): void
    {
        $flush = static fn () => app(\App\Support\Connectors\MarkupRules::class)->flush();
        static::saved($flush);
        static::deleted($flush);
    }

    protected $table = 'normalized_tiers_rules';

    protected $fillable = ['from_price', 'to_price', 'from_quantity_1', 'from_quantity_2', 'from_quantity_3', 'from_quantity_4'];
}
