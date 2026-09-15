<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Models\Brand;
use App\Models\Product;
use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BrandData
{
    /**
     * Slug from the name when empty; logo stored as a public URL path
     * (config('brand') and the storefront read absolute paths).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalise(array $data): array
    {
        if (trim((string) ($data['slug'] ?? '')) === '') {
            $data['slug'] = Str::slug((string) ($data['name'] ?? ''));
        }
        if (! empty($data['logo']) && ! str_starts_with((string) $data['logo'], '/')) {
            $data['logo'] = '/storage/'.ltrim((string) $data['logo'], '/');
        }

        return $data;
    }

    /** products.brand is the denormalised label the storefront reads. */
    public static function syncProducts(Model $brand): void
    {
        if ($brand instanceof Brand) {
            Product::query()->where('brand_id', $brand->id)->update(['brand' => $brand->name]);
        }
        CatalogCache::flush();
    }
}
