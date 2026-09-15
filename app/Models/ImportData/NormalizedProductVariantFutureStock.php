<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NormalizedProductVariantFutureStock extends Model
{
    protected $table = 'normalized_products_variants_future_stocks';

    protected $fillable = [
        'variant_id',
        'date',
        'quantity',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(NormalizedProductVariant::class, 'variant_id');
    }
}
