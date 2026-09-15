<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductColorFamily extends Model
{
    use SoftDeletes;

    protected $table = 'product_colors_families';

    protected $fillable = [
        'label',
        'code',
    ];

    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class, 'family_id');
    }
}
