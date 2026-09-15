<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationArea;
use App\Models\Customizations\CustomizationOption;
use App\Models\Customizations\CustomizationTier;
use App\Support\Connectors\CustomizationPipeline;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * After a customizations import: drop the customizations of live pipelines
 * that the import did not touch for `--days` (the supplier no longer offers
 * them; the foreign keys cascade to areas, options and tiers), then any
 * orphaned child rows. Runs in chunks.
 */
final class CleanupCustomizations extends Command
{
    protected $signature = 'cleanup:customizations {--days=30 : Retention in days since the last import touch} {--dry-run : Report without deleting}';

    protected $description = 'Delete customizations no import has touched for the retention period, and orphaned child rows';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);
        $this->info(sprintf('Customizations cleanup: retention %d days (before %s), %s', $days, $cutoff->format('Y-m-d H:i:s'), $dryRun ? 'dry run' : 'live'));

        $stale = CustomizationPipeline::apply(Customization::query()->where('updated_at', '<', $cutoff));
        $count = (clone $stale)->count();
        $this->line("Stale customizations: {$count}");
        if ($count > 0 && ! $dryRun) {
            $deleted = 0;
            (clone $stale)->select(['id', 'source', 'source_product_sku'])->chunkById(500, function ($rows) use (&$deleted): void {
                $deleted += Customization::query()->whereIn('id', $rows->pluck('id'))->delete();
            });
            $this->line("Deleted: {$deleted}");
        }

        $orphans = [
            'areas' => CustomizationArea::query()->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('customizations')->whereColumn('customizations.id', 'customization_areas.parent_id')),
            'options' => CustomizationOption::query()->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('customization_areas')->whereColumn('customization_areas.id', 'customization_options.parent_id')),
            'tiers' => CustomizationTier::query()->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('customization_options')->whereColumn('customization_options.id', 'customization_tiers.parent_id')),
        ];
        foreach ($orphans as $name => $query) {
            $orphanCount = (clone $query)->count();
            $this->line("Orphaned {$name}: {$orphanCount}");
            if ($orphanCount > 0 && ! $dryRun) {
                $query->delete();
            }
        }

        return self::SUCCESS;
    }
}
