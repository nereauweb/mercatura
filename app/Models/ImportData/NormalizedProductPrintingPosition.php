<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NormalizedProductPrintingPosition extends Pivot
{
    protected $table = 'normalized_printing_position_normalized_product';

    protected $fillable = [
        'normalized_product_id',
        'normalized_printing_position_id',
        'image',
        'ref',
        'is_default',
        'print_days',
    ];

    public $incrementing = true;
}
