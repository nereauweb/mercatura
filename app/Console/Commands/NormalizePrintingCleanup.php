<?php

namespace App\Console\Commands;

use App\Models\Customizations\Customization;
use App\Models\Customizations\CustomizationArea;
use App\Models\Customizations\CustomizationOption;
use App\Models\Customizations\CustomizationTier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizePrintingCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:customizations {--days=30 : Number of days to retain data} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old printing variant data and orphaned records';

    /**
     * Number of days to retain data (configurable)
     */
    protected $retentionDays;

    /**
     * Whether this is a dry run (no actual deletion)
     */
    protected $isDryRun;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->retentionDays = (int) $this->option('days');
        $this->isDryRun = $this->option('dry-run');

        $cutoffDate = Carbon::now()->subDays($this->retentionDays);

        $this->info('🧹 Starting printing variants cleanup...');
        $this->info("📅 Retention period: {$this->retentionDays} days");
        $this->info("📅 Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->info('🔍 Mode: '.($this->isDryRun ? 'DRY RUN' : 'LIVE DELETE'));
        $this->line('');

        $this->cleanupOldPrintingVariants($cutoffDate);
        $this->cleanupOrphanedRecords();

        $this->line('');
        $this->info('✅ Cleanup completed!');
    }

    /**
     * Clean up old printing variants and their related data
     */
    private function cleanupOldPrintingVariants(Carbon $cutoffDate): void
    {
        $this->info('🗑️  Cleaning up old printing variants...');

        // Get old printing variants
        // Only live pipelines are cleaned (each connector declares its live one).
        $oldPrintings = \App\Support\Connectors\CustomizationPipeline::apply(Customization::where('updated_at', '<', $cutoffDate))->get();

        if ($oldPrintings->isEmpty()) {
            $this->warn('   No old printing variants found to delete.');

            return;
        }

        $this->info("   Found {$oldPrintings->count()} old printing variants to delete.");

        if ($this->isDryRun) {
            $this->warn('   [DRY RUN] Would delete the following printing variants:');
            foreach ($oldPrintings as $printing) {
                $this->line("   - ID: {$printing->id}, Source: {$printing->source}, SKU: {$printing->source_product_sku}, Updated: {$printing->updated_at}");
            }

            return;
        }

        $deletedCount = 0;

        foreach ($oldPrintings as $printing) {
            $this->line("   Deleting printing variant ID: {$printing->id} ({$printing->source} - {$printing->source_product_sku})");

            // Delete related data in correct order (child to parent)
            $this->deletePrintingRelatedData($printing);

            // Delete the printing variant itself
            $printing->delete();
            $deletedCount++;
        }

        $this->info("   ✅ Deleted {$deletedCount} old printing variants and their related data.");
    }

    /**
     * Delete all data related to a printing variant
     */
    private function deletePrintingRelatedData(Customization $printing): void
    {
        // Get all sizes for this printing
        $sizes = $printing->areas;

        foreach ($sizes as $size) {
            // Get all colors for this size
            $colors = $size->options;

            foreach ($colors as $color) {
                // Delete all prices for this color
                $color->tiers()->delete();
                $this->line("     Deleted prices for color ID: {$color->id}");
            }

            // Delete all colors for this size
            $size->options()->delete();
            $this->line("   Deleted colors for size ID: {$size->id}");
        }

        // Delete all sizes for this printing
        $printing->areas()->delete();
        $this->line(" Deleted sizes for printing ID: {$printing->id}");
    }

    /**
     * Clean up orphaned records
     */
    private function cleanupOrphanedRecords(): void
    {
        $this->info('🔍 Cleaning up orphaned records...');

        $this->cleanupOrphanedSizes();
        $this->cleanupOrphanedColors();
        $this->cleanupOrphanedPrices();

        $this->info('   ✅ Orphaned records cleanup completed.');
    }

    /**
     * Clean up orphaned printing sizes
     */
    private function cleanupOrphanedSizes(): void
    {
        // Find sizes without parent printing
        $orphanedSizes = CustomizationArea::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('customizations')
                ->whereColumn('customizations.id', 'customization_areas.parent_id');
        })->get();

        if ($orphanedSizes->isEmpty()) {
            $this->line('   No orphaned sizes found.');

            return;
        }

        $this->warn("   Found {$orphanedSizes->count()} orphaned sizes.");

        if ($this->isDryRun) {
            foreach ($orphanedSizes as $size) {
                $this->line("   [DRY RUN] Would delete orphaned size ID: {$size->id} (parent_id: {$size->parent_id})");
            }

            return;
        }

        $deletedCount = 0;
        foreach ($orphanedSizes as $size) {
            // Delete related colors and prices first
            $this->deleteSizeRelatedData($size);
            $size->delete();
            $deletedCount++;
        }

        $this->info("   ✅ Deleted {$deletedCount} orphaned sizes.");
    }

    /**
     * Clean up orphaned printing colors
     */
    private function cleanupOrphanedColors(): void
    {
        // Find colors without parent size
        $orphanedColors = CustomizationOption::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('customization_areas')
                ->whereColumn('customization_areas.id', 'customization_options.parent_id');
        })->get();

        if ($orphanedColors->isEmpty()) {
            $this->line('   No orphaned colors found.');

            return;
        }

        $this->warn("   Found {$orphanedColors->count()} orphaned colors.");

        if ($this->isDryRun) {
            foreach ($orphanedColors as $color) {
                $this->line("   [DRY RUN] Would delete orphaned color ID: {$color->id} (parent_id: {$color->parent_id})");
            }

            return;
        }

        $deletedCount = 0;
        foreach ($orphanedColors as $color) {
            // Delete related prices first
            $color->tiers()->delete();
            $color->delete();
            $deletedCount++;
        }

        $this->info("   ✅ Deleted {$deletedCount} orphaned colors.");
    }

    /**
     * Clean up orphaned printing prices
     */
    private function cleanupOrphanedPrices(): void
    {
        // Find prices without parent color
        $orphanedPrices = CustomizationTier::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('customization_options')
                ->whereColumn('customization_options.id', 'customization_tiers.parent_id');
        })->get();

        if ($orphanedPrices->isEmpty()) {
            $this->line('   No orphaned prices found.');

            return;
        }

        $this->warn("   Found {$orphanedPrices->count()} orphaned prices.");

        if ($this->isDryRun) {
            foreach ($orphanedPrices as $price) {
                $this->line("   [DRY RUN] Would delete orphaned price ID: {$price->id} (parent_id: {$price->parent_id})");
            }

            return;
        }

        $deletedCount = $orphanedPrices->count();
        CustomizationTier::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('customization_options')
                ->whereColumn('customization_options.id', 'customization_tiers.parent_id');
        })->delete();

        $this->info("   ✅ Deleted {$deletedCount} orphaned prices.");
    }

    /**
     * Delete all data related to a printing size
     */
    private function deleteSizeRelatedData(CustomizationArea $size): void
    {
        // Get all colors for this size
        $colors = $size->options;

        foreach ($colors as $color) {
            // Delete all prices for this color
            $color->tiers()->delete();
        }

        // Delete all colors for this size
        $size->options()->delete();
    }
}
