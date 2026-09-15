<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemArticle extends Model
{
    protected $table = 'order_item_articles';

    protected $fillable = [
        'item_id',
        'article_id',
        'article_sku',
        'article_size_label',
        'article_color_label',
        'article_image_url',
        'quantity',
        'unit_price',
        'price',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'article_id');
    }
}
