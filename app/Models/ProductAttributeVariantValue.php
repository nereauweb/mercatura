<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductAttributeVariantValue extends Pivot
{
    protected $table = 'products_variants_attributes';

    protected $fillable = [
        'product_id', // bigint unsigned
        'variant_id', // bigint unsigned nullable
        'attribute_id', // bigint unsigned
        'value',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'id', 'variant_id');
    }
}
