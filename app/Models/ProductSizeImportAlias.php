<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSizeImportAlias extends Model
{
    protected $table = 'product_sizes_import_aliases';

    protected $fillable = [
        'size_id',
        'source',
        'alias',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }
}
