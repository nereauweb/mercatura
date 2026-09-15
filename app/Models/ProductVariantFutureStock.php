<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantPrice extends Model
{
    protected $table = 'products_variants_future_stocks';

    protected $fillable = [
        'variant_id',
        'date',
        'quantity',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
