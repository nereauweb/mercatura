<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;

class NormalizedProductVariantImage extends Model
{
    protected $table = 'normalized_products_variants_images';

    protected $fillable = [
        'variant_id',
        'filename',
        'url',
        'main',
    ];
}
