<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $table = 'pages_contents';

    protected $fillable = [
        'page_id',
        'filter_type',
        'filter_target',
        'filter_value',
    ];

    public function product()
    {
        if (! $this->filter_type == 'product_id') {
            return false;
        }
        $product = Product::find($this->filter_target);

        return $product;
    }

    public function category_products()
    {
        if (! $this->filter_type == 'category_id') {
            return false;
        }
        $category = Category::find($this->filter_target);

        return $category->ordered_products()->pluck('products.id');
    }

    public function sale_products()
    {
        if (! $this->filter_type == 'is_sale') {
            return false;
        }

        return Product::whereHas('main_variant_relationship', function ($sq) {
            $sq->where('isSale', '>', 0);
        })->get();
    }
}
