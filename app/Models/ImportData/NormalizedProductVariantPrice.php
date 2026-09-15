<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormalizedProductVariantPrice extends Model
{
    protected $table = 'normalized_products_variants_prices';

    protected $fillable = [
        'variant_id',
        'from_quantity',
        'price',
        'original_price',
        'included_additional_costs',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(NormalizedProductVariant::class, 'variant_id');
    }
}
