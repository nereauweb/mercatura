<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Brand;
use App\Models\Product;

/**
 * Brand logo shown on product cards.
 *
 * The brand record (Catalogo → Marchi) wins: an active brand with a logo shows it, an
 * inactive brand shows nothing. Products not linked to a brand fall back to the legacy
 * file public/img/brands/{brand_image} written by the import. Brand logos are loaded
 * once per request, so a listing does not query per card.
 */
final class BrandLogo
{
    /** @return array{url: string, alt: string}|null */
    public static function for(Product $product): ?array
    {
        $alt = (string) $product->brand;
        if ($product->brand_id) {
            $logo = self::logos()[(int) $product->brand_id] ?? null;

            return $logo !== null ? ['url' => $logo, 'alt' => $alt] : null;
        }
        $file = (string) $product->brand_image;
        if ($file !== '' && is_file(public_path('img/brands/'.$file))) {
            return ['url' => '/img/brands/'.$file, 'alt' => $alt];
        }

        return null;
    }

    /** @return array<int, string> active brand id => logo URL path */
    private static function logos(): array
    {
        return once(fn (): array => Brand::query()->where('active', true)->whereNotNull('logo')->where('logo', '!=', '')
            ->pluck('logo', 'id')->map(fn ($logo): string => (string) $logo)->all());
    }
}
