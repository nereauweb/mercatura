<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MessageType;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Storage limitation for user reports (docs/06_USER_REPORTS.md §4, R7): the
 * attachments and screen captures of reports older than the retention period
 * are deleted; the report text stays for the admin. Scheduled daily.
 */
final class CleanupReportAttachments extends Command
{
    protected $signature = 'cleanup:report-attachments {--days= : Retention in days since the report was sent (default: mercatura.reports.attachment_retention_days; 0 keeps everything)} {--dry-run : Report without deleting}';

    protected $description = 'Delete the attachments of user reports older than the retention period, keeping the reports';

    public function handle(): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('mercatura.reports.attachment_retention_days', 90);
        if ($days <= 0) {
            $this->info('Report attachments: retention off, nothing deleted.');

            return self::SUCCESS;
        }
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $query = Message::query()
            ->where('type', MessageType::Report->value)
            ->where('created_at', '<', $cutoff)
            ->whereHas('media', fn (Builder $media) => $media->where('collection_name', Message::ATTACHMENTS));

        $reports = 0;
        $files = 0;
        $query->chunkById(100, function ($messages) use (&$reports, &$files, $dryRun): void {
            foreach ($messages as $message) {
                $reports++;
                $files += $message->getMedia(Message::ATTACHMENTS)->count();
                if (! $dryRun) {
                    $message->clearMediaCollection(Message::ATTACHMENTS);
                }
            }
        });

        $this->info(sprintf('Report attachments: %d files of %d reports sent before %s %s.', $files, $reports, $cutoff->format('Y-m-d H:i'), $dryRun ? 'would be deleted (dry run)' : 'deleted'));

        return self::SUCCESS;
    }
}
