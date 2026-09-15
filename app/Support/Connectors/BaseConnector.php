<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Contracts\ImportConnector;
use App\Models\ImportData\NormalizedProductVariant;
use App\Models\Product;
use App\Models\ProductMarkup;
use App\Models\ProductVariant;
use Filament\Contracts\Plugin;

/** Neutral defaults for every ImportConnector hook; a connector overrides what its supplier needs. */
abstract class BaseConnector implements ImportConnector
{
    public const DEFAULT_PROCESSING_DAYS = 7;

    public function sourceAliases(): array
    {
        return [];
    }

    public function sourceValues(): array
    {
        return array_values(array_unique([$this->key(), $this->label(), ...$this->sourceAliases()]));
    }

    /**
     * The installation switch: the package's own `connector-<key>.enabled`
     * (env MERCATURA_CONNECTOR_<KEY>), overridable from mercatura.features.connectors.<key>.
     */
    public function enabled(): bool
    {
        $override = config('mercatura.features.connectors.'.$this->key());

        return (bool) ($override ?? config('connector-'.$this->key().'.enabled', false));
    }

    public function commands(string $stage): array
    {
        return [];
    }

    public function processingDays(Product $product): int
    {
        return self::DEFAULT_PROCESSING_DAYS;
    }

    public function variantDimensions(ProductVariant $variant): ?string
    {
        return null;
    }

    public function markupPercent(float $percent, ?ProductMarkup $rule, float $condition, int $condition3, ?string $sku): float
    {
        return $percent;
    }

    public function markupSeries(Product $product): int
    {
        return ProductMarkup::SERIES_STANDARD;
    }

    public function appliesMarkupToSingleTier(?string $sku): bool
    {
        return false;
    }

    public function printingPipelines(): ?array
    {
        return null;
    }

    public function shouldDeactivateVariant(NormalizedProductVariant $variant): bool
    {
        return false;
    }

    public function rawProductData(Product $product): array
    {
        return [];
    }

    public function rawVariantData(ProductVariant $variant): array
    {
        return [];
    }

    public function catalogFilterLabel(): ?string
    {
        return null;
    }

    public function adminPlugin(): ?Plugin
    {
        return null;
    }

    /** True when a source value (any spelling) belongs to this connector. */
    public function matchesSource(?string $source): bool
    {
        if ($source === null || $source === '') {
            return false;
        }
        foreach ($this->sourceValues() as $value) {
            if (strcasecmp($value, $source) === 0) {
                return true;
            }
        }

        return false;
    }
}
