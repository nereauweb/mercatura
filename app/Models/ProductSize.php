<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSize extends Model
{
    use SoftDeletes;

    protected $table = 'product_sizes';

    protected $fillable = [
        'type_id',
        'label',
    ];

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'size_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductSizeType::class, 'type_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductSizeAlias::class, 'size_id');
    }

    public function shown_label()
    {
        if ($this->label == 'N/A') {
            return '';
        }

        return $this->label;
    }
}
