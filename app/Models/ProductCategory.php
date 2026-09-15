<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProductCategory extends Pivot
{
    protected $table = 'category_product';

    protected $fillable = [
        'category_id', // bigint unsigned
        'product_id', // bigint unsigned
        'position', // bigint unsigned
    ];
}
