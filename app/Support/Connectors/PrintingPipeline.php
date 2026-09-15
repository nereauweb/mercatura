<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\ImportData\VariantPrinting;
use App\Models\ImportData\VariantPrintingColor;
use App\Support\ImportConnectors;
use Illuminate\Database\Eloquent\Builder;

/**
 * A connector may keep several printing pipelines in printing_variants
 * (e.g. an older price list next to a new API feed) and declare which one
 * is live (ImportConnector::printingPipelines). The storefront only reads
 * live rows; sources without a declaration are always live.
 */
final class PrintingPipeline
{
    /**
     * @param  Builder<VariantPrinting>  $query
     * @return Builder<VariantPrinting>
     */
    public static function apply(Builder $query): Builder
    {
        foreach (app(ImportConnectors::class)->all() as $connector) {
            $pipelines = $connector->printingPipelines();
            if ($pipelines === null) {
                continue;
            }
            $sources = $connector->sourceValues();
            $query->where(function (Builder $inner) use ($sources, $pipelines): void {
                $inner->whereNotIn('source', $sources)->orWhereIn('pipeline', $pipelines);
            });
        }

        return $query;
    }

    public static function printingIsLive(?VariantPrinting $printing): bool
    {
        if (! $printing) {
            return false;
        }
        $pipelines = app(ImportConnectors::class)->forSource($printing->source)?->printingPipelines();

        return $pipelines === null || in_array((string) $printing->pipeline, $pipelines, true);
    }

    public static function colorIsLive(?VariantPrintingColor $color): bool
    {
        if (! $color) {
            return false;
        }
        try {
            return self::printingIsLive($color->printing_size->printing);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
