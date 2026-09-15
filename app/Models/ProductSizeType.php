<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSizeType extends Model
{
    use SoftDeletes;

    protected $table = 'product_size_types';

    protected $fillable = [
        'label',
    ];

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class, 'type_id');
    }
}
