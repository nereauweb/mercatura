<?php

namespace App\Console\Commands;

use App\Support\CaughtExceptionLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FixMediaFileExtensions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:FixMediaFileExtensions {--dry-run : Run without making actual changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add missing file extensions to media files based on their mime type';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY RUN mode - no changes will be made');
        }

        $this->info('Starting to fix media file extensions...');

        // Recupera tutti i media della collection 'image'
        $mediaItems = Media::where('collection_name', 'image')
            ->orderBy('id')
            ->get();

        $total = $mediaItems->count();
        $processed = 0;
        $fixed = 0;
        $errors = 0;

        $this->info("Found {$total} media items to check");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($mediaItems as $media) {
            $processed++;

            try {
                // Verifica se il file_name ha già un'estensione
                $currentExtension = pathinfo($media->file_name, PATHINFO_EXTENSION);

                if (! empty($currentExtension)) {
                    // Ha già un'estensione, skip
                    $bar->advance();

                    continue;
                }

                // Determina l'estensione dal mime type
                $extension = match ($media->mime_type) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    'image/bmp' => 'bmp',
                    'image/svg+xml' => 'svg',
                    default => null,
                };

                if (! $extension) {
                    $this->newLine();
                    $this->warn("Unknown mime type '{$media->mime_type}' for media ID {$media->id}");
                    $errors++;
                    $bar->advance();

                    continue;
                }

                // Prepara i nuovi nomi
                $oldFileName = $media->file_name;
                $newFileName = $oldFileName.'.'.$extension;

                $disk = Storage::disk($media->disk);
                $oldPath = $media->getPath();
                $newPath = $media->getPathRelativeToRoot();
                $newPath = str_replace($oldFileName, $newFileName, $newPath);

                if (! $dryRun) {
                    // Rinomina il file fisico
                    if ($disk->exists($oldPath)) {
                        $fullOldPath = $disk->path($oldPath);
                        $fullNewPath = dirname($fullOldPath).'/'.$newFileName;

                        if (! rename($fullOldPath, $fullNewPath)) {
                            $this->newLine();
                            $this->error("Failed to rename file for media ID {$media->id}");
                            $errors++;
                            $bar->advance();

                            continue;
                        }
                    } else {
                        $this->newLine();
                        $this->warn("File not found for media ID {$media->id}: {$oldPath}");
                        // Aggiorna comunque il database
                    }

                    // Aggiorna il database
                    $media->file_name = $newFileName;
                    $media->save();

                    // Rigenera le conversioni (thumbs, etc.)
                    if ($media->model_type === 'App\Models\ProductVariant') {
                        try {
                            $variant = $media->model;
                            if ($variant) {
                                $media->manipulations = [];
                                $media->generated_conversions = [];
                                $media->save();

                                if ($disk->exists($newPath)) {
                                    app(FileManipulator::class)->createDerivedFiles($media);
                                }
                            }
                        } catch (\Exception $e) {
                            CaughtExceptionLogger::error('FixMediaFileExtensions: media conversion refresh failed', $e, [
                                'media_id' => $media->id,
                            ]);
                        }
                    }

                    $fixed++;
                } else {
                    $this->newLine();
                    $this->line("Would rename: {$oldFileName} -> {$newFileName} (Media ID: {$media->id})");
                    $fixed++;
                }

            } catch (\Exception $e) {
                CaughtExceptionLogger::error('FixMediaFileExtensions: media item processing failed', $e, [
                    'media_id' => $media->id,
                ]);
                $this->newLine();
                $this->error("Error processing media ID {$media->id}: ".$e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Riepilogo
        $this->info('Process completed!');
        $this->line("Total media items checked: {$total}");
        $this->line('Files '.($dryRun ? 'that would be fixed' : 'fixed').": {$fixed}");
        $this->line("Errors: {$errors}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a DRY RUN - no changes were made');
            $this->info('Run without --dry-run to apply changes');
        }
    }
}
