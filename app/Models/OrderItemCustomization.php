<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Customizations\CustomizationOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customization chosen on an order item, as sold: technique, position,
 * area, option and amounts snapshotted at checkout (the option id is only
 * a pointer, null once the supplier row is gone), plus the artwork file the
 * customer uploads afterwards. One row per option chosen on the line; the
 * quantity is the line quantity, `price` the option's total across the
 * line's articles. Setup, start and surcharge are order_item_extras rows.
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $option_id
 * @property string|null $family
 * @property string|null $technique_label
 * @property string|null $position_label
 * @property string|null $area_label
 * @property string|null $option_label
 * @property int|null $number_of_colors
 * @property int|null $quantity
 * @property float|null $price
 * @property float|null $packaging_price
 * @property string|null $label
 * @property string|null $file
 */
class OrderItemCustomization extends Model
{
    protected $table = 'order_item_customizations';

    protected $fillable = ['item_id', 'option_id', 'family', 'technique_label', 'position_label', 'area_label', 'option_label', 'number_of_colors', 'quantity', 'price', 'packaging_price', 'label', 'file'];

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }

    /** @return HasMany<OrderItemExtra, $this> */
    public function extras(): HasMany
    {
        return $this->hasMany(OrderItemExtra::class, 'customization_id');
    }

    /** @return BelongsTo<CustomizationOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CustomizationOption::class, 'option_id');
    }
}
