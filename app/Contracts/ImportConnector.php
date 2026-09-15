<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ImportData\NormalizedProductVariant;
use App\Models\ImportData\NormalizedRulesPricingProducts;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Contracts\Plugin;

/**
 * A supplier catalogue connector, shipped as its own package
 * (docs/ARCHITECTURE.md §13). The core knows the normalized layer
 * (normalized_products*, printing_variants*, normalized_rules_*) and the
 * final catalogue; a connector owns everything before it: download, raw
 * tables, normalisation, and the supplier rules the storefront needs at
 * runtime (delivery days, dimensions, markup exceptions, live printing
 * pipelines). Extend App\Support\Connectors\BaseConnector: it implements
 * every hook with the neutral default so a connector overrides only what
 * its supplier needs.
 *
 * Stages map to artisan commands run by the import jobs, in order:
 * download raw data, normalize products, normalize printings.
 */
interface ImportConnector
{
    public const STAGE_DOWNLOAD = 'download';

    public const STAGE_PRODUCTS = 'products';

    public const STAGE_PRINTINGS = 'printings';

    /** Feature flag key and canonical source value, e.g. "acme". */
    public function key(): string;

    /** Human label, e.g. "Acme". */
    public function label(): string;

    /**
     * Legacy spellings of this source found in existing data
     * (products.source, normalized_products.source, printing_variants.source,
     * categories_import_aliases.source), e.g. ["Acme", "ACME"].
     *
     * @return list<string>
     */
    public function sourceAliases(): array;

    /** Every value that identifies this connector as a source: key, label and aliases. @return list<string> */
    public function sourceValues(): array;

    public function enabled(): bool;

    /**
     * Artisan commands for a stage, in execution order.
     *
     * @return list<string>
     */
    public function commands(string $stage): array;

    /** Delivery days before printing for a product of this source (legacy Product::processing_days). */
    public function processingDays(Product $product): int;

    /** Dimensions label for a variant, or null when the supplier has none. */
    public function variantDimensions(ProductVariant $variant): ?string;

    /**
     * Markup percent for an order value, after the generic band lookup:
     * the connector may raise or replace it (supplier exceptions).
     *
     * @param  float  $percent  the band value, 0 when no band matched
     * @param  float  $condition  quantity × unit cost
     * @param  int  $condition3  0 normal, 1 web-shop
     */
    public function markupPercent(float $percent, ?NormalizedRulesPricingProducts $rule, float $condition, int $condition3, ?string $sku): float;

    /** Whether a single-tier price (no band) still gets the markup applied (supplier exception). */
    public function appliesMarkupToSingleTier(?string $sku): bool;

    /** Printing pipelines currently live for this source (printing_variants.pipeline); null means all. @return list<string>|null */
    public function printingPipelines(): ?array;

    /** Whether the normalized → catalogue step must deactivate this variant (supplier end-of-series flags). */
    public function shouldDeactivateVariant(NormalizedProductVariant $variant): bool;

    /** Raw supplier data of a product, for the admin (empty when not kept). @return array<string, mixed> */
    public function rawProductData(Product $product): array;

    /** Raw supplier data of a variant, for the admin. @return array<string, mixed> */
    public function rawVariantData(ProductVariant $variant): array;

    /** Label of the storefront catalogue filter that selects this source, or null for no filter. */
    public function catalogFilterLabel(): ?string;

    /** Filament plugin registering the connector's admin pages and resources, or null. */
    public function adminPlugin(): ?Plugin;
}
