<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMarkup extends Model
{
    protected $table = 'product_markups';

    protected $fillable = [
        'starting_from_value',
        'markup_percent',
    ];
}
