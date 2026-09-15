<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'product_sku',
        'product_name',
        'product_image_url',
        'quantity',
        'price',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany('App\Models\OrderItemArticle', 'item_id');
    }

    public function printings(): HasMany
    {
        return $this->hasMany('App\Models\OrderItemPrinting', 'item_id');
    }

    public function extras(): HasMany
    {
        return $this->hasMany('App\Models\OrderItemExtra', 'item_id');
    }
}
