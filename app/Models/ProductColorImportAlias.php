<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductColorImportAlias extends Model
{
    protected $table = 'product_colors_import_aliases';

    protected $fillable = [
        'color_id',
        'source',
        'alias',
    ];

    public function color(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'color_id');
    }
}
