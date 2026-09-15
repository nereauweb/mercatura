<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A markup band: for an order value (quantity × unit cost) between
 * from_condition (exclusive) and to_condition (inclusive), the selling price is
 * the cost plus `value` percent. The same bands serve the import (on the
 * quantities of the price-list tiers) and the storefront (on the quantity
 * actually bought). Read through App\Support\Connectors\MarkupRules; edited
 * in the admin (Sistema → Regole di prezzo); seeded by CoreSeeder.
 *
 * @property int $id
 * @property float $from_condition
 * @property float $to_condition
 * @property float $value
 */
class ProductMarkup extends Model
{
    protected $table = 'product_markups';

    protected $fillable = ['from_condition', 'to_condition', 'value'];
}
