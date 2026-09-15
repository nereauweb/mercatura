<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantPrice extends Model
{
    protected $table = 'products_variants_prices';

    protected $fillable = [
        'variant_id',
        'from_quantity',
        'price',
        'original_price',
        'included_additional_costs',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
