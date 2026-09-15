<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaCategory extends Model
{
    protected $table = 'meta_categories';

    protected $fillable = [
        'parent_id',
        'magento_entity_id',
        'magento_parent_id',
        'name',
        'active',
        'existent',
        'size_label',
    ];

    public function aliases(): HasMany
    {
        return $this->hasMany(CategoryAlias::class, 'category_id', 'id');
    }
}
