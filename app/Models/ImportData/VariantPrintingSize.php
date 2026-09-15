<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantPrintingSize extends Model
{
    protected $table = 'printing_variants_sizes';

    protected $fillable = [
        'parent_id',
        'label',
        'type',
        'width_mm',
        'height_mm',
    ];

    /** @return BelongsTo<VariantPrinting, $this> */
    public function printing(): BelongsTo
    {
        return $this->BelongsTo(VariantPrinting::class, 'parent_id');
    }

    /** @return HasMany<VariantPrintingColor, $this> */
    public function printing_colors(): HasMany
    {
        return $this->HasMany(VariantPrintingColor::class, 'parent_id');
    }
}
