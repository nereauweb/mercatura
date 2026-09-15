<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fixed amount on an order item, typed: the setup and start cost of a
 * customization (linked to its snapshot row), the under-minimum surcharge,
 * or anything else. Written at checkout from the priced line.
 *
 * @property int $id
 * @property int $item_id
 * @property string $type
 * @property int|null $customization_id
 * @property string $label
 * @property float $price
 */
class OrderItemExtra extends Model
{
    public const TYPE_SETUP = 'setup';

    public const TYPE_START = 'start';

    public const TYPE_SURCHARGE = 'surcharge';

    public const TYPE_OTHER = 'other';

    protected $table = 'order_item_extras';

    protected $fillable = ['item_id', 'type', 'customization_id', 'label', 'price'];

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'item_id');
    }

    /** @return BelongsTo<OrderItemCustomization, $this> */
    public function customization(): BelongsTo
    {
        return $this->belongsTo(OrderItemCustomization::class, 'customization_id');
    }
}
