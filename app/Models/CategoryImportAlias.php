<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryImportAlias extends Model
{
    protected $table = 'categories_import_aliases';

    protected $fillable = [
        'category_id',
        'source',
        'parent_category_ref',
        'category_ref',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
