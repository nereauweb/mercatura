<?php

declare(strict_types=1);

namespace App\Support\Connectors;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationOption;
use App\Support\ImportConnectors;
use Illuminate\Database\Eloquent\Builder;

/**
 * A connector may keep several printing pipelines in customizations
 * (e.g. an older price list next to a new API feed) and declare which one
 * is live (ImportConnector::customizationPipelines). The storefront only reads
 * live rows; sources without a declaration are always live. It also reads
 * only active rows: a customization the source stopped listing is switched
 * off by cleanup:customizations (and back on when it reappears); the admin
 * lists every row through the relations that pass $onlyActive = false.
 */
final class CustomizationPipeline
{
    /**
     * @param  Builder<Customization>  $query
     * @return Builder<Customization>
     */
    public static function apply(Builder $query, bool $onlyActive = true): Builder
    {
        if ($onlyActive) {
            $query->where($query->qualifyColumn('active'), true);
        }
        foreach (app(ImportConnectors::class)->all() as $connector) {
            $pipelines = $connector->customizationPipelines();
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

    public static function isLive(?Customization $printing): bool
    {
        if (! $printing) {
            return false;
        }
        $pipelines = app(ImportConnectors::class)->forSource($printing->source)?->customizationPipelines();

        return $pipelines === null || in_array((string) $printing->pipeline, $pipelines, true);
    }

    public static function optionIsLive(?CustomizationOption $color): bool
    {
        if (! $color) {
            return false;
        }
        try {
            return self::isLive($color->area->customization);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
