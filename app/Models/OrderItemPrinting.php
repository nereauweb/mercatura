<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemPrinting extends Model
{
    protected $table = 'order_item_printings';

    protected $fillable = [
        'item_id',
        'printing_variant_color_id',
        'printing_label',
        'print_file',
    ];

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }

    /** The option (technique + position + size + colours) the customer chose. @return BelongsTo<\App\Models\ImportData\VariantPrintingColor, $this> */
    public function printing(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ImportData\VariantPrintingColor::class, 'printing_variant_color_id');
    }
}
