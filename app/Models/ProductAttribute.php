<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttribute extends Model
{
    use SoftDeletes;

    protected $table = 'product_attributes';

    protected $fillable = [
        'label',
        'alias',
    ];

    /*
    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAttributeAlias::class, 'attribute_id');
    }
    */

    /** @return BelongsToMany<ProductVariant, $this, ProductAttributeVariantValue> */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'products_variants_attributes', 'attribute_id', 'variant_id')->using(ProductAttributeVariantValue::class);
    }
}
