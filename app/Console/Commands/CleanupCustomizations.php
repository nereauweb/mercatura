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
 * that the import did not touch for `--days` (default from config, 90) (the supplier no longer offers
 * them; the foreign keys cascade to areas, options and tiers), then any
 * orphaned child rows. Runs in chunks.
 */
final class CleanupCustomizations extends Command
{
    protected $signature = 'cleanup:customizations {--days= : Retention in days since the last import touch (default: mercatura.customizations.cleanup_days)} {--dry-run : Report without deleting}';

    protected $description = 'Delete customizations no import has touched for the retention period, and orphaned child rows';

    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('mercatura.customizations.cleanup_days', 90);
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subDays($days);
        $this->info(sprintf('Customizations cleanup: retention %d days (before %s), %s', $days, $cutoff->format('Y-m-d H:i:s'), $dryRun ? 'dry run' : 'live'));

        $this->reconcile($dryRun);

        // Only what an import may delete: unlocked rows of connector sources, live pipelines,
        // not listed by the source for the retention period (0 = never delete).
        $stale = CustomizationPipeline::apply(Customization::query()->importable(), false)
            ->where(fn ($q) => $q->where('last_seen_at', '<', $cutoff)->orWhere(fn ($q2) => $q2->whereNull('last_seen_at')->where('updated_at', '<', $cutoff)));
        $count = $days > 0 ? (clone $stale)->count() : 0;
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

    /**
     * Lifecycle (docs/03_CUSTOMIZATIONS.md §7): a live-pipeline row the source
     * stopped listing for missing_days is switched off; one listed again is
     * switched back on. Manual and locked rows are never touched.
     */
    private function reconcile(bool $dryRun): void
    {
        $missingDays = (int) config('mercatura.customizations.missing_days', 3);
        $seenCutoff = Carbon::now()->subDays($missingDays);
        $importable = fn () => CustomizationPipeline::apply(Customization::query()->importable(), false);

        $toDeactivate = $importable()->where('active', true)
            ->where(fn ($q) => $q->where('last_seen_at', '<', $seenCutoff)->orWhere(fn ($q2) => $q2->whereNull('last_seen_at')->where('updated_at', '<', $seenCutoff)));
        $toReactivate = $importable()->where('active', false)->where('last_seen_at', '>=', $seenCutoff);

        $deactivate = (clone $toDeactivate)->count();
        $reactivate = (clone $toReactivate)->count();
        $this->line("Not listed by the source for {$missingDays} days, to deactivate: {$deactivate}");
        $this->line("Listed again, to reactivate: {$reactivate}");
        if (! $dryRun) {
            // updated_at keeps meaning "last data change": the switch is not one.
            if ($deactivate > 0) {
                $toDeactivate->update(['active' => false, 'updated_at' => DB::raw('`updated_at`')]);
            }
            if ($reactivate > 0) {
                $toReactivate->update(['active' => true, 'updated_at' => DB::raw('`updated_at`')]);
            }
        }
    }
}
