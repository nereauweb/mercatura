<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A markup band: for an order value (quantity × unit cost) between
 * condition_1 (exclusive) and condition_2 (inclusive), the selling price is
 * the cost plus `value` percent. The same bands serve the import (on the
 * quantities of the price-list tiers) and the storefront (on the quantity
 * actually bought). Read through App\Support\Connectors\MarkupRules; edited
 * in the admin (Sistema → Regole di prezzo); seeded by CoreSeeder.
 *
 * @property int $id
 * @property float $condition_1
 * @property float $condition_2
 * @property float $value
 */
class ProductMarkup extends Model
{
    protected $table = 'product_markups';

    protected $fillable = ['condition_1', 'condition_2', 'value'];
}
