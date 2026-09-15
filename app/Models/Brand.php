<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Product brand. products.brand keeps the label for the storefront; products.brand_id links here. */
class Brand extends Model
{
    use HasSeoFields;
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo', 'description', 'active', 'position', 'seo_title', 'seo_description', 'canonical_url', 'noindex', 'og_title', 'og_description', 'og_image'];

    protected $casts = ['active' => 'boolean', 'noindex' => 'boolean', 'position' => 'integer'];

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    public function defaultPath(): string
    {
        return '/prodotti/marchi/'.rawurlencode($this->name);
    }
}
