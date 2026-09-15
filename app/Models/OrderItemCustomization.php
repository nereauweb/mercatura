<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Customizations\CustomizationOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customization chosen on an order item: the option and its label as
 * sold, and the artwork file the customer uploads afterwards.
 *
 * @property int $id
 * @property int $item_id
 * @property int $option_id
 * @property string|null $label
 * @property string|null $file
 */
class OrderItemCustomization extends Model
{
    protected $table = 'order_item_customizations';

    protected $fillable = ['item_id', 'option_id', 'label', 'file'];

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }

    /** @return BelongsTo<CustomizationOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CustomizationOption::class, 'option_id');
    }
}
