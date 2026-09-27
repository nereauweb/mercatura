<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ContentHomeSlide;
use App\Support\HomeSlideImages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Generates web (800) and large (1600) WebP sidecars for every home slideshow image. */
final class RegenerateHomeSlideImages extends Command
{
    protected $signature = 'mercatura:regenerate-home-slide-images';

    protected $description = 'Generate web/large WebP conversions for home slideshow images.';

    public function handle(): int
    {
        $count = 0;
        ContentHomeSlide::query()->withTrashed()->each(function (ContentHomeSlide $slide) use (&$count): void {
            HomeSlideImages::convert($slide->background_image);
            HomeSlideImages::convert($slide->mobile_image);
            $count++;
        });
        $tiles = 0;
        \App\Models\ContentHomeTile::query()->withTrashed()->each(function (\App\Models\ContentHomeTile $tile) use (&$tiles): void {
            \App\Support\HomeTileImages::convert($tile->image);
            $tiles++;
        });
        Cache::forget('home_slides');
        Cache::forget('home_tiles');
        $this->info("Converted images for {$count} slides and {$tiles} tiles.");

        return self::SUCCESS;
    }
}
