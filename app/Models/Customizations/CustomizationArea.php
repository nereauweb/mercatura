<?php

declare(strict_types=1);

namespace App\Models\Customizations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The extent of a customization (a print size, an embroidery field…):
 * label, shape (`type`), millimetres, supplier-specific `supplier_data`.
 *
 * @property int $id
 * @property int $parent_id
 * @property string $label
 * @property string|null $type
 * @property int|null $width_mm
 * @property int|null $height_mm
 */
class CustomizationArea extends Model
{
    protected $table = 'customization_areas';

    protected $fillable = ['parent_id', 'label', 'type', 'width_mm', 'height_mm', 'supplier_data'];

    protected $casts = ['supplier_data' => 'array'];

    /** @return BelongsTo<Customization, $this> */
    public function customization(): BelongsTo
    {
        return $this->belongsTo(Customization::class, 'parent_id');
    }

    /** @return HasMany<CustomizationOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CustomizationOption::class, 'parent_id');
    }
}
