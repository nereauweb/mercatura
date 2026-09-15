<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Category;
use App\Models\ImportData\VariantPrinting;
use App\Models\ImportData\VariantPrintingColor;
use App\Models\ImportData\VariantPrintingSize;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use Database\Seeders\CoreSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Categories, colours, products with variants, prices, attributes, pictures and printing options. */
class DemoCatalogSeeder extends Seeder
{
    public const SKU_PREFIX = 'DEMO-';

    public const SOURCE = 'own';

    /** @var array<string, ProductColor> */
    private array $colors = [];

    /** @var array<string, Category> */
    private array $leaves = [];

    /** @var array<string, ProductSize> */
    private array $sizes = [];

    public function run(): void
    {
        $this->seedColors();
        $this->seedCategories();
        $this->sizes = ProductSize::query()->get()->keyBy('label')->all();

        $attributes = (array) config('mercatura.catalog.attributes');
        $counter = 0;
        foreach (Catalog::products() as $definition) {
            $counter++;
            $this->seedProduct($counter, $definition, $attributes);
        }
    }

    private function seedColors(): void
    {
        foreach (Catalog::COLORS as $label => $hex) {
            $this->colors[$label] = ProductColor::query()->firstOrCreate(['label' => $label], ['code' => $hex]);
        }
    }

    private function seedCategories(): void
    {
        $position = 0;
        foreach (Catalog::CATEGORIES as $name => [$hex, $description, $children]) {
            $icon = 'demo-'.str($name)->slug().'.png';
            Storage::disk('public')->put('categories/icons/'.$icon, Images::icon($hex, mb_substr($name, 0, 1)));
            $root = Category::query()->create([
                'name' => $name, 'slug' => str($name)->slug()->toString(), 'description' => $description,
                'icon' => $icon, 'icon_rev' => $icon, 'position' => $position++, 'active' => 1,
                'seo_title' => $name.' personalizzati', 'seo_description' => $description,
            ]);
            $childPosition = 0;
            foreach ($children as $child) {
                $this->leaves[$child] = Category::query()->create([
                    'parent_id' => $root->id, 'name' => $child, 'slug' => str($child)->slug()->toString(),
                    'position' => $childPosition++, 'active' => 1,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, int>  $attributes
     */
    private function seedProduct(int $n, array $d, array $attributes): void
    {
        $sku = sprintf('%s%03d', self::SKU_PREFIX, $n);
        $createdAt = now()->subMonths((int) $d['age'])->subDays($n);

        $product = Product::query()->create([
            'sku' => $sku, 'source' => self::SOURCE, 'source_sku' => $sku, 'active' => 1, 'forced_status' => 'none',
            'name' => $d['name'], 'description' => $d['description'], 'brand' => $d['brand'],
            'default_print_technique' => array_values($d['positions'])[0][0], 'default_print_position' => array_key_first($d['positions']),
            'isGreen' => in_array('green', $d['flags'], true) ? 1 : 0,
            'isPromo' => in_array('promo', $d['flags'], true) ? 1 : 0,
            'isBestseller' => in_array('bestseller', $d['flags'], true) ? 1 : 0,
        ]);
        // Backdate so "new" filters and the novità page have something to distinguish.
        Product::query()->whereKey($product->id)->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        $product->refresh();
        $product->slug();
        $product->categories()->attach($this->leaves[$d['category']]->id, ['position' => $n]);

        $sizes = $d['sizes'] ? CoreSeeder::SIZES : ['Unica'];
        $variantColors = [];
        $variantIndex = 0;
        /** @var ProductVariant|null $first */
        $first = null;
        foreach ($d['colors'] as $colorLabel) {
            $color = $this->colors[$colorLabel];
            $image = Images::product(Catalog::COLORS[$colorLabel], $d['shape'], $sku.' '.$colorLabel);
            foreach ($sizes as $sizeLabel) {
                $variantIndex++;
                $variant = ProductVariant::query()->create([
                    'product_id' => $product->id, 'sku' => sprintf('%s-%02d', $sku, $variantIndex), 'active' => 1,
                    'isSale' => in_array('sale', $d['flags'], true) ? 1 : 0,
                    'color_id' => $color->id, 'size_id' => $this->sizes[$sizeLabel]->id,
                    'stock' => 120 + (($n * 37 + $variantIndex * 53) % 900), 'next_stock_quantity' => 0,
                    'source' => strtoupper(self::SOURCE), 'source_sku' => sprintf('%s-%02d', $sku, $variantIndex),
                ]);
                $first ??= $variant;
                $variantColors[$variant->id] = $color->id;

                $this->seedPrices($variant, (float) $d['cost'], (int) $d['min']);
                $this->seedAttributes($product, $variant, $d, $attributes);
                $variant->addMediaFromString($image)->usingFileName($variant->sku.'.jpg')->usingName($d['name'].' '.$colorLabel)->toMediaCollection('image');
                $this->seedPrintings($product, $variant, $d);
            }
        }

        $product->variants_colors = json_encode($variantColors);
        $product->save();
        $product->set_main_variant($first instanceof ProductVariant ? $first->id : false, true);
    }

    private function seedPrices(ProductVariant $variant, float $cost, int $min): void
    {
        foreach (Catalog::PRODUCT_TIERS as $quantity) {
            if ($quantity < $min) {
                continue;
            }
            $markup = CoreSeeder::markupPercent($quantity * $cost);
            ProductVariantPrice::query()->create([
                'variant_id' => $variant->id, 'from_quantity' => $quantity,
                'price' => round($cost * (1 + $markup / 100), 2), 'original_price' => $cost, 'included_additional_costs' => 0,
            ]);
        }
        if ($min > 1 && ! in_array($min, Catalog::PRODUCT_TIERS, true)) {
            $markup = CoreSeeder::markupPercent($min * $cost);
            ProductVariantPrice::query()->create([
                'variant_id' => $variant->id, 'from_quantity' => $min,
                'price' => round($cost * (1 + $markup / 100), 2), 'original_price' => $cost, 'included_additional_costs' => 0,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $d
     * @param  array<string, int>  $attributes
     */
    private function seedAttributes(Product $product, ProductVariant $variant, array $d, array $attributes): void
    {
        [$pieces, $length, $width, $height, $weight] = $d['pack'];
        $values = [
            $attributes['brand'] ?? 3 => $d['brand'],
            $attributes['material'] ?? 4 => $d['material'],
            6 => $d['dimensions'],
            7 => array_values($d['positions'])[0][0],
            8 => array_key_first($d['positions']),
            $attributes['pack_pieces'] ?? 11 => $pieces,
            $attributes['pack_length'] ?? 12 => number_format($length, 2, '.', ''),
            $attributes['pack_width'] ?? 13 => number_format($width, 2, '.', ''),
            $attributes['pack_height'] ?? 14 => number_format($height, 2, '.', ''),
            $attributes['pack_weight'] ?? 15 => number_format($weight, 2, '.', ''),
            17 => strtolower($d['name'].', '.$d['category']),
        ];
        $rows = [];
        foreach ($values as $attributeId => $value) {
            $rows[] = ['product_id' => $product->id, 'variant_id' => $variant->id, 'attribute_id' => $attributeId, 'value' => (string) $value];
        }
        DB::table('products_variants_attributes')->insert($rows);
    }

    /**
     * One printing row per position × technique, each with one size, its
     * colour options and price tiers derived from the technique preset.
     *
     * @param  array<string, mixed>  $d
     */
    private function seedPrintings(Product $product, ProductVariant $variant, array $d): void
    {
        $isDefault = true;
        foreach ($d['positions'] as $positionLabel => $techniques) {
            [$sizeLabel, $width, $height] = Catalog::POSITIONS[$positionLabel];
            foreach ($techniques as $technique) {
                [$colorOptions, $days] = Catalog::TECHNIQUES[$technique];
                $printing = VariantPrinting::query()->create([
                    'source' => self::SOURCE, 'pipeline' => self::SOURCE, 'source_product_sku' => $product->sku, 'source_variant_sku' => $variant->sku,
                    'product_id' => $product->id, 'variant_id' => $variant->id,
                    'technique_label' => $technique, 'position_label' => $positionLabel,
                    'position_code' => strtoupper(substr(str($positionLabel)->slug()->toString(), 0, 3)),
                    'technique_main_code' => strtoupper(substr(str($technique)->slug()->toString(), 0, 3)),
                    'image' => null, 'is_default' => $isDefault ? 1 : 0, 'processing_days' => $days, 'has_packaging' => 0,
                    'minimum_quantity' => max(1, (int) $d['min']), 'max_colors' => (string) count($colorOptions), 'max_print_position' => count($d['positions']),
                ]);
                $isDefault = false;
                $size = VariantPrintingSize::query()->create(['parent_id' => $printing->id, 'label' => $sizeLabel, 'type' => 'rectangle', 'width_mm' => $width, 'height_mm' => $height]);
                foreach ($colorOptions as [$label, $numberOfColors, $multiplier, $setup]) {
                    $color = VariantPrintingColor::query()->create([
                        'parent_id' => $size->id, 'label' => $label, 'number_of_colors' => $numberOfColors, 'setup_multiplier' => 1,
                        'setup' => round($setup * 1.2, 2), 'original_setup' => $setup, 'start_cost' => 0, 'original_start_cost' => 0,
                    ]);
                    $prices = [];
                    foreach (Catalog::PRINT_TIERS as $quantity => $unitCost) {
                        $cost = round($unitCost * $multiplier, 2);
                        $markup = CoreSeeder::markupPercent($quantity * (float) $d['cost']);
                        $prices[] = [
                            'parent_id' => $color->id, 'from_quantity' => $quantity, 'price' => round($cost * (1 + $markup / 100), 2),
                            'price_method_1' => 0, 'price_method_2' => 0, 'original_price' => $cost,
                            'packaging_price' => 0, 'packaging_price_method_1' => 0, 'packaging_price_method_2' => 0, 'packaging_original_price' => 0,
                            'created_at' => now(), 'updated_at' => now(),
                        ];
                    }
                    DB::table('printing_variants_prices')->insert($prices);
                }
            }
        }
    }
}
