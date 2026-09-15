<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationArea;
use App\Models\Customizations\CustomizationOption;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Illuminate\Support\Facades\DB;

/**
 * A small, fully known catalogue for the customization characterisation
 * tests (docs/03_CUSTOMIZATIONS.md §4.9): one product, two variants with
 * the same price tiers, screen printing on both, embroidery on the first
 * only. Every number is chosen so that the expected totals can be computed
 * by hand from the formulas of §4.3. Requires CoreSeeder (markup bands).
 */
final class CustomizationFixture
{
    /** Product tiers: from quantity => unit cost. Every tier carries 0.10 of additional (VAT-free) costs. */
    public const PRODUCT_TIERS = [1 => 10.00, 50 => 9.00, 100 => 8.00];

    /** Print tiers: from quantity => [unit cost, packaging unit price (stored), packaging unit cost]. */
    public const PRINT_TIERS = [1 => [1.00, 0.65, 0.50], 50 => [0.80, 0.65, 0.50], 100 => [0.60, 0.65, 0.50]];

    public Product $product;

    public ProductVariant $a;

    public ProductVariant $b;

    public Customization $screenA;

    public Customization $embroideryA;

    public Customization $screenB;

    /** Serigrafia, 1 colour: setup 30 × 1, start cost 5. */
    public CustomizationOption $screenOneColorA;

    /** Serigrafia, 2 colours: setup 30 × 2, no start cost. */
    public CustomizationOption $screenTwoColorsA;

    /** Ricamo "Fino a 12": setup 60 with setup_multiplier 0 (docs/03_CUSTOMIZATIONS.md §2.4 defect 7). */
    public CustomizationOption $embroideryOptionA;

    public CustomizationOption $screenOneColorB;

    public static function create(): self
    {
        $f = new self;
        $blue = ProductColor::query()->create(['label' => 'Blu', 'code' => '#0000ff']);
        $red = ProductColor::query()->create(['label' => 'Rosso', 'code' => '#ff0000']);

        $f->product = Product::query()->create([
            'sku' => 'FIX-001', 'source' => 'own', 'source_sku' => 'FIX-001', 'active' => 1, 'forced_status' => 'none',
            'name' => 'Fixture product', 'description' => 'Fixture', 'default_customization_technique' => 'Serigrafia', 'default_customization_position' => 'Fronte',
        ]);
        $f->product->refresh();
        $f->product->slug();

        $f->a = self::variant($f->product, 'FIX-001-01', $blue->id);
        $f->b = self::variant($f->product, 'FIX-001-02', $red->id);
        $f->product->set_main_variant($f->a->id, true);

        $f->screenA = self::printing($f->product, $f->a, 'Serigrafia', 'Fronte', isDefault: true, days: 5, packaging: true);
        $screenSizeA = CustomizationArea::query()->create(['parent_id' => $f->screenA->id, 'label' => '10x10', 'type' => 'rectangle', 'width_mm' => 100, 'height_mm' => 100]);
        $f->screenOneColorA = self::option($screenSizeA, '1', 1, 1, 30.00, 5.00);
        $f->screenTwoColorsA = self::option($screenSizeA, '2', 2, 2, 30.00, 0.00);

        $f->embroideryA = self::printing($f->product, $f->a, 'Ricamo', 'Retro', isDefault: false, days: 6, packaging: false);
        $embroiderySizeA = CustomizationArea::query()->create(['parent_id' => $f->embroideryA->id, 'label' => '8x8', 'type' => 'rectangle', 'width_mm' => 80, 'height_mm' => 80]);
        $f->embroideryOptionA = self::option($embroiderySizeA, 'Fino a 12', 1, 0, 60.00, 0.00);

        $f->screenB = self::printing($f->product, $f->b, 'Serigrafia', 'Fronte', isDefault: true, days: 5, packaging: true);
        $screenSizeB = CustomizationArea::query()->create(['parent_id' => $f->screenB->id, 'label' => '10x10', 'type' => 'rectangle', 'width_mm' => 100, 'height_mm' => 100]);
        $f->screenOneColorB = self::option($screenSizeB, '1', 1, 1, 30.00, 5.00);
        self::option($screenSizeB, '2', 2, 2, 30.00, 0.00);

        return $f;
    }

    private static function variant(Product $product, string $sku, int $colorId): ProductVariant
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => $sku, 'active' => 1, 'isSale' => 0, 'color_id' => $colorId,
            'size_id' => (int) config('mercatura.catalog.one_size_id', 52), 'stock' => 1000, 'next_stock_quantity' => 0,
            'source' => 'OWN', 'source_sku' => $sku,
        ]);
        foreach (self::PRODUCT_TIERS as $quantity => $cost) {
            ProductVariantPrice::query()->create([
                'variant_id' => $variant->id, 'from_quantity' => $quantity, 'price' => $cost * 2, 'original_price' => $cost, 'included_additional_costs' => 0.10,
            ]);
        }

        return $variant->refresh();
    }

    private static function printing(Product $product, ProductVariant $variant, string $technique, string $position, bool $isDefault, int $days, bool $packaging): Customization
    {
        return Customization::query()->create([
            'source' => 'own', 'pipeline' => 'own', 'source_product_sku' => $product->sku, 'source_variant_sku' => $variant->sku,
            'product_id' => $product->id, 'variant_id' => $variant->id, 'technique_label' => $technique, 'position_label' => $position,
            'position_code' => strtoupper(substr($position, 0, 3)), 'technique_main_code' => strtoupper(substr($technique, 0, 3)),
            'image' => null, 'is_default' => $isDefault ? 1 : 0, 'processing_days' => $days, 'has_packaging' => $packaging ? 1 : 0,
            'minimum_quantity' => 50, 'max_colors' => '2', 'max_print_position' => 2,
        ]);
    }

    private static function option(CustomizationArea $size, string $label, int $numberOfColors, int $multiplier, float $setup, float $start): CustomizationOption
    {
        $option = CustomizationOption::query()->create([
            'parent_id' => $size->id, 'label' => $label, 'number_of_colors' => $numberOfColors, 'setup_multiplier' => $multiplier,
            'setup' => $setup, 'original_setup' => round($setup / 1.2, 2), 'start_cost' => $start, 'original_start_cost' => $start,
        ]);
        $rows = [];
        foreach (self::PRINT_TIERS as $quantity => [$cost, $packagingPrice, $packagingCost]) {
            // `price` is deliberately wrong (9.99): the storefront must recompute from original_price with the article markup.
            $rows[] = [
                'parent_id' => $option->id, 'from_quantity' => $quantity, 'price' => 9.99, 'original_price' => $cost,
                'packaging_price' => $packagingPrice, 'packaging_original_price' => $packagingCost,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('customization_tiers')->insert($rows);

        return $option;
    }
}
