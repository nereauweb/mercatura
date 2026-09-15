<?php

namespace App\Models\ImportData;

use App\Support\CaughtExceptionLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariantPrintingColor extends Model
{
    protected $table = 'printing_variants_colors';

    protected $fillable = [
        'parent_id',
        'label',
        'number_of_colors',
        'setup_multiplier',
        'setup',
        'original_setup',
        'start_cost',
        'original_start_cost',
    ];

    /** @return BelongsTo<VariantPrintingSize, $this> */
    public function printing_size(): BelongsTo
    {
        return $this->BelongsTo(VariantPrintingSize::class, 'parent_id');
    }

    /** @return HasMany<VariantPrintingPrice, $this> */
    public function printing_prices(): HasMany
    {
        return $this->HasMany(VariantPrintingPrice::class, 'parent_id');
    }

    public function label()
    {
        if ($this->label == '1') {
            return '1 colore';
        } elseif ($this->label == '0') {
            return 'Quadricromia';
        } elseif (! is_numeric($this->label)) {
            return $this->label;
        }

        return $this->label.' colori';
    }

    public function printing_label()
    {
        return strtoupper($this->printing_size->printing->position_label).' - '.$this->printing_size->printing->technique_label.' '.($this->printing_size->getAttribute('printing_label') ?? '').' '.$this->printing_size->label.' '.$this->label();
    }

    public function setup_label()
    {
        return 'Setup '.$this->printing_size->printing->technique_label.' '.$this->printing_size->printing->position_label;
    }

    public function start_label()
    {
        return 'Avviamento';
    }

    public function sibling($variant_id)
    {
        try {
            $printing = $this->printing_size->printing;
            $sibling_query = VariantPrinting::where('product_id', $printing->product_id)->where('variant_id', $variant_id)->where('technique_label', $printing->technique_label)->where('position_label', $printing->position_label);
            // A connector may keep several pipelines per source (PrintingPipeline): siblings live in the same one.
            if ($printing->pipeline !== null) {
                $sibling_query->where('pipeline', $printing->pipeline);
            }
            $sibling_printing = $sibling_query->first();
            $sibling_size = $sibling_printing->printing_sizes()->where('label', $this->printing_size->label)->first();
            $sibling_color = $sibling_size->printing_colors()->where('label', $this->label)->first();
        } catch (\Error $e) {
            CaughtExceptionLogger::error('VariantPrintingColor::sibling failed', $e, [
                'printing_color_id' => $this->id,
                'variant_id' => $variant_id,
            ]);

            return $this;
        }

        return empty($sibling_color) ? $this : $sibling_color;
    }

    public function calculate_print_price($total_quantity, $quantity, $with_packaging = false, $use_original_price = false, $markup_percent = false)
    {
        $print_price = 0;
        $packaging_price = 0;
        foreach ($this->printing_prices()->orderBy('from_quantity', 'desc')->get() as $price) {
            if ($price->from_quantity <= $total_quantity) {
                // $print_price = floatval($use_original_price ? $price->original_price : $price->price);
                if ($use_original_price) {
                    $print_price = $price->original_price;
                } else {
                    $print_price = $markup_percent ? round($price->original_price * (1 + $markup_percent / 100), 2) : $price->price;
                }
                $packaging_price = floatval($use_original_price ? $price->packaging_original_price : $price->packaging_price);
                break;
            }
        }
        $return = [
            'unit_price' => floatval($print_price),
            'quantity' => intval($quantity),
            'price' => $quantity * $print_price,
        ];
        if ($with_packaging) {
            $return['packaging_quantity'] = intval($quantity);
            $return['packaging_unit_price'] = floatval($packaging_price);
            $return['packaging_price'] = $quantity * $packaging_price;
        }

        return $return;
    }
}
