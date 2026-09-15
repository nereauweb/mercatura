<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeImportAlias extends Model
{
    protected $table = 'product_attributes_import_aliases';

    protected $fillable = [
        'product_attribute_id', // bigint unsigned
        'source', // bigint unsigned
        'alias', // bigint unsigned nullable
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'size_id');
    }
}
