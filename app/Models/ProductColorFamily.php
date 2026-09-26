<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductColorFamily extends Model
{
    use SoftDeletes;

    protected $table = 'product_colors_families';

    protected $fillable = [
        'label',
        'code',
    ];

    /** @return BelongsToMany<ProductColor, $this> */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(ProductColor::class, 'product_colors_families_colors', 'family_id', 'color_id')->withTimestamps();
    }
}
