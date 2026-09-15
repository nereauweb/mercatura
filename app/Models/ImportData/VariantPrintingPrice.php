<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantPrintingPrice extends Model
{
    protected $table = 'printing_variants_prices';

    protected $fillable = [
        'parent_id',
        'from_quantity',
        'price',
        'original_price',
        'packaging_price',
        'packaging_original_price',
    ];

    public function printing_color(): BelongsTo
    {
        return $this->BelongsTo(VariantPrintingColor::class, 'parent_id');
    }

    public function calculate_markup_price($article = false, $quantity = false, $save = false)
    {
        if (! $quantity) {
            $quantity = $this->from_quantity;
        }
        if (! $article) {
            $article = $this->printing_color->printing_size->printing->variant; // normalized variant during import?
        }
        $article_original_price = $article->price_per_quantity($quantity, true);
        $article_markup_percent = $article->get_markup_percent($quantity, $article_original_price);
        $this->price = round($this->original_price * (1 + ($article_markup_percent / 100)), 2);
        if ($this->packaging_original_price > 0) {
            $this->packaging_price = round($this->packaging_original_price * (1 + ($article_markup_percent / 100)), 2);
        }
        if ($save) {
            $this->save();
        }

        return $this->price;
    }
}
