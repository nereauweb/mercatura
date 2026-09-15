<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;

class NormalizedProductVariantColor extends Model
{
    protected $table = 'normalized_products_variants_colors';

    protected $fillable = [
        'variant_id',
        'hex_code',
        'label',
        'family',
    ];
}
