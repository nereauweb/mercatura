<?php

declare(strict_types=1);

namespace App\Models\Customizations;

use App\Support\CaughtExceptionLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The priced option of an area: colours for a print, threads for an
 * embroidery, one option for an engraving. Carries the fixed costs (setup
 * × multiplier, start cost) and the quantity tiers.
 *
 * @property int $id
 * @property int $parent_id
 * @property string $label
 * @property int $number_of_colors
 * @property int $setup_multiplier
 * @property float $setup
 * @property float $original_setup
 * @property float $start_cost
 * @property float $original_start_cost
 */
class CustomizationOption extends Model
{
    protected $table = 'customization_options';

    protected $fillable = ['parent_id', 'label', 'number_of_colors', 'setup_multiplier', 'setup', 'original_setup', 'start_cost', 'original_start_cost'];

    /** @return BelongsTo<CustomizationArea, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(CustomizationArea::class, 'parent_id');
    }

    /** @return HasMany<CustomizationTier, $this> */
    public function tiers(): HasMany
    {
        return $this->hasMany(CustomizationTier::class, 'parent_id');
    }

    /** The option as the customer reads it ("1 colore", "Quadricromia", "Fino a 12"). */
    public function label(): string
    {
        if ($this->label == '1') {
            return __('frontend.customization.one_colour');
        }
        if ($this->label == '0') {
            return __('frontend.customization.full_colour');
        }
        if (! is_numeric($this->label)) {
            return (string) $this->label;
        }

        return __('frontend.customization.n_colours', ['count' => $this->label]);
    }

    /** "POSITION - technique  area option", the line label stored on orders. */
    public function fullLabel(): string
    {
        $customization = $this->area->customization;

        // The area label is preceded by a space kept from the legacy format (a second, never-set label once sat there).
        return __('frontend.customization.full_label', ['position' => strtoupper((string) $customization->position_label), 'technique' => $customization->technique_label, 'area' => ' '.$this->area->label, 'option' => $this->label()]);
    }

    public function setupLabel(): string
    {
        $customization = $this->area->customization;

        return __('frontend.customization.setup', ['technique' => $customization->technique_label, 'position' => $customization->position_label]);
    }

    public function startLabel(): string
    {
        return __('frontend.customization.start');
    }

    /**
     * The same option on another variant of the product: same technique and
     * position (and pipeline), same area label, same option label. Falls
     * back to this option when the variant has no equivalent.
     */
    public function equivalentFor(int $variantId): self
    {
        try {
            $customization = $this->area->customization;
            $query = Customization::query()->where('product_id', $customization->product_id)->where('variant_id', $variantId)
                ->where('technique_label', $customization->technique_label)->where('position_label', $customization->position_label);
            if ($customization->pipeline !== null) {
                $query->where('pipeline', $customization->pipeline);
            }
            $sibling = $query->first();
            $area = $sibling?->areas()->where('label', $this->area->label)->first();
            $option = $area?->options()->where('label', $this->label)->first();
        } catch (\Throwable $e) {
            CaughtExceptionLogger::error('CustomizationOption::equivalentFor failed', $e, ['option_id' => $this->id, 'variant_id' => $variantId]);

            return $this;
        }

        return $option ?? $this;
    }

    /**
     * Price of this option for an article: the tier is chosen by the line
     * quantity, the amount multiplies the article quantity. With a markup
     * percent the cost is re-marked-up; without, the stored (import-time)
     * price is used; `useOriginalPrice` returns the bare cost.
     *
     * @return array{unit_price: float, quantity: int, price: float, packaging_quantity?: int, packaging_unit_price?: float, packaging_price?: float}
     */
    public function priceFor(int|float $totalQuantity, int|float $quantity, bool $withPackaging = false, bool $useOriginalPrice = false, float|false $markupPercent = false): array
    {
        $unitPrice = 0.0;
        $packagingPrice = 0.0;
        foreach ($this->tiers()->orderBy('from_quantity', 'desc')->get() as $tier) {
            if ($tier->from_quantity <= $totalQuantity) {
                if ($useOriginalPrice) {
                    $unitPrice = (float) $tier->original_price;
                } else {
                    $unitPrice = $markupPercent ? round((float) $tier->original_price * (1 + $markupPercent / 100), 2) : (float) $tier->price;
                }
                $packagingPrice = (float) ($useOriginalPrice ? $tier->packaging_original_price : $tier->packaging_price);
                break;
            }
        }
        $result = ['unit_price' => $unitPrice, 'quantity' => (int) $quantity, 'price' => $quantity * $unitPrice];
        if ($withPackaging) {
            $result['packaging_quantity'] = (int) $quantity;
            $result['packaging_unit_price'] = $packagingPrice;
            $result['packaging_price'] = $quantity * $packagingPrice;
        }

        return $result;
    }
}
