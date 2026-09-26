<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Illuminate\Console\Command;

/**
 * Regenerates the named WebP conversions (thumb, web, large) for every
 * variant image, then rewrites products.cover_url to the new thumb path.
 * Do not run while a supplier import is writing media.
 */
final class RegenerateProductImages extends Command
{
    protected $signature = 'mercatura:regenerate-product-images
        {--only-missing : Skip conversions that already exist}
        {--force : Allow the command to run in production}';

    protected $description = 'Regenerate variant WebP conversions then product cover URLs. Do not run during a supplier import.';

    public function handle(): int
    {
        $this->warn('Do not run this while a supplier import is writing media.');

        $regenerate = [
            'modelType' => ProductVariant::class,
            '--only' => [
                ProductVariant::MEDIA_CONVERSION_THUMB,
                ProductVariant::MEDIA_CONVERSION_WEB,
                ProductVariant::MEDIA_CONVERSION_LARGE,
            ],
            '--force' => (bool) $this->option('force'),
        ];
        if ($this->option('only-missing')) {
            $regenerate['--only-missing'] = true;
        }

        $this->sanitizeUnreadableOriginals();
        $this->call('media-library:regenerate', $regenerate);
        $this->call('app:GenerateProductsCover');

        return self::SUCCESS;
    }

    /**
     * Originals libmagic cannot read never get their conversions (the storefront
     * then serves the multi-MB file): re-encode them before regenerating.
     */
    private function sanitizeUnreadableOriginals(): void
    {
        $fixed = 0;
        \Spatie\MediaLibrary\MediaCollections\Models\Media::query()
            ->where('model_type', ProductVariant::class)->where('collection_name', 'image')
            ->where(fn ($q) => $q->whereNull('generated_conversions')->orWhere('generated_conversions', '[]')->orWhere('generated_conversions', 'not like', '%'.ProductVariant::MEDIA_CONVERSION_THUMB.'%'))
            ->orderBy('id')->chunkById(200, function ($rows) use (&$fixed): void {
                foreach ($rows as $media) {
                    if (\App\Support\ImageSanitizer::prepare($media->getPath())) {
                        $fixed++;
                    }
                }
            });
        $this->line("Unreadable originals re-encoded: {$fixed}");
    }
}
