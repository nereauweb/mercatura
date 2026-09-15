<?php

declare(strict_types=1);

namespace App\Models\Customizations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quantity tier of an option: `original_price` is the supplier cost per
 * piece, `price` the same with the article's markup baked in at import.
 *
 * @property int $id
 * @property int $parent_id
 * @property int $from_quantity
 * @property float $price
 * @property float $original_price
 * @property float|null $packaging_price
 * @property float|null $packaging_original_price
 */
class CustomizationTier extends Model
{
    protected $table = 'customization_tiers';

    protected $fillable = ['parent_id', 'from_quantity', 'price', 'original_price', 'packaging_price', 'packaging_original_price'];

    /** @return BelongsTo<CustomizationOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CustomizationOption::class, 'parent_id');
    }

    /** Bake the article's markup for this tier's quantity into `price` (and the packaging price), as the imports do. */
    public function applyMarkup($article = false, $quantity = false, bool $save = false): float
    {
        if (! $quantity) {
            $quantity = $this->from_quantity;
        }
        if (! $article) {
            $article = $this->option->area->customization->variant;
        }
        $articleOriginalPrice = $article->price_per_quantity($quantity, true);
        $markupPercent = $article->get_markup_percent($quantity, $articleOriginalPrice);
        $this->price = round($this->original_price * (1 + ($markupPercent / 100)), 2);
        if ($this->packaging_original_price > 0) {
            $this->packaging_price = round($this->packaging_original_price * (1 + ($markupPercent / 100)), 2);
        }
        if ($save) {
            $this->save();
        }

        return (float) $this->price;
    }
}
