<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemExtra extends Model
{
    protected $table = 'order_item_extras';

    protected $fillable = [
        'item_id',
        'label',
        'price',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }
}
