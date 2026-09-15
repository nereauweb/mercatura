<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $table = 'quotations_items';

    protected $fillable = [
        'quotation_id',
        'sku',
        'name',
        'quantity',
        'printing',
        'image',
        'color',
        'size',
        'notes',
    ];

    public function quotation(): belongsTo
    {
        return $this->belongsTo(Order::class, 'quotation_id');
    }
}
