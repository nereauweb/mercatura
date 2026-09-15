<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProductAttribute;
use App\Models\ProductSize;
use App\Models\ProductSizeType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reference rows every installation needs, demo or not: roles, the product
 * attributes the storefront reads (config mercatura.catalog.attributes),
 * size types with the "one size" row, and the markup bands used by the
 * pricing helpers. Idempotent: existing rows are kept.
 */
class CoreSeeder extends Seeder
{
    /** Attribute id => [label, alias]; ids match the config defaults. */
    public const ATTRIBUTES = [
        2 => ['Peso', 'weightG'],
        3 => ['Marchio', 'brand'],
        4 => ['Materiale', 'material'],
        5 => ['Paese d\'origine', 'countryOfOrigin'],
        6 => ['Dimensioni', 'dimensions'],
        7 => ['Tipologia di decorazione consigliata', 'printing_default_technique'],
        8 => ['Posizione di stampa consigliata', 'printing_default_location'],
        9 => ['Dimensione di stampa consigliata', 'printing_default_dimension'],
        10 => ['Numero massimo di colori stampabili', 'printing_default_max_colors'],
        11 => ['Pezzi per scatola', 'qtyPerCarton'],
        12 => ['Lunghezza scatola', 'packageDepthCm'],
        13 => ['Larghezza scatola', 'packageWidthCm'],
        14 => ['Altezza scatola', 'packageHeightCm'],
        15 => ['Peso lordo scatola', 'packagingGrossWeightKg'],
        16 => ['Peso netto scatola', 'packagingNetWeightKg'],
        17 => ['Keywords', 'keywords'],
        18 => ['Tema', 'theme'],
    ];

    /**
     * Markup bands: [from order value, to order value, markup %]. The order
     * value is quantity × unit cost; the same bands apply to products and
     * printings (condition_3 = 0 and 1).
     */
    public const MARKUP_BANDS = [
        [0, 100, 100.0], [100, 150, 90.0], [150, 200, 80.0], [200, 250, 65.0], [250, 350, 60.0],
        [350, 400, 55.0], [400, 450, 50.0], [450, 500, 45.0], [500, 550, 40.0], [550, 600, 35.0],
        [600, 1000, 30.0], [1000, 2500, 25.0], [2500, 5000, 20.0], [5000, 10000, 15.0], [10000, 100000000, 10.0],
    ];

    public const SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

    /** Admin panel permissions (docs/02_V2B_ADMIN.md §3.3); the admin role holds all of them. */
    public const PERMISSIONS = ['catalog.manage', 'orders.manage', 'content.manage', 'imports.run', 'settings.manage'];

    public function run(): void
    {
        foreach (['admin', 'customer'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Role::findByName('admin', 'web')->givePermissionTo(self::PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ATTRIBUTES as $id => [$label, $alias]) {
            ProductAttribute::query()->firstOrCreate(['id' => $id], ['label' => $label, 'alias' => $alias]);
        }

        $type = ProductSizeType::query()->firstOrCreate(['label' => 'Taglia']);
        foreach (self::SIZES as $label) {
            ProductSize::query()->firstOrCreate(['type_id' => $type->id, 'label' => $label]);
        }
        $oneSizeId = (int) config('mercatura.catalog.one_size_id', 52);
        ProductSize::query()->firstOrCreate(['id' => $oneSizeId], ['type_id' => $type->id, 'label' => 'Unica']);

        if (DB::table('product_markups')->count() === 0) {
            $rows = [];
            foreach ([0, 1] as $condition3) {
                foreach (self::MARKUP_BANDS as [$from, $to, $percent]) {
                    $rows[] = ['condition_type' => 0, 'condition_1' => $from, 'condition_2' => $to, 'condition_3' => $condition3, 'delta_type' => 0, 'value' => $percent, 'created_at' => now()];
                }
            }
            DB::table('product_markups')->insert($rows);
        }
    }

    /** Markup percent for an order value (quantity × unit cost), as the pricing helpers compute it. */
    public static function markupPercent(float $orderValue): float
    {
        foreach (self::MARKUP_BANDS as [$from, $to, $percent]) {
            if ($from < $orderValue && $orderValue <= $to) {
                return $percent;
            }
        }

        return 0.0;
    }
}
