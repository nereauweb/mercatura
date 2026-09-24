<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ContentHomeSlide;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Named WebP sidecars for home slideshow files on the public disk (not Spatie
 * media: slides store a bare filename). Same widths as ProductVariant: web 800,
 * large 1600, quality 80. Originals stay in home_slides/.
 *
 * @phpstan-type Banner array{src: string, srcset: string, webp: bool}
 */
final class HomeSlideImages
{
    public const DIRECTORY = 'home_slides';

    public const WEB = 'web';

    public const LARGE = 'large';

    /** @var array<string, int> */
    public const WIDTHS = [
        self::WEB => 800,
        self::LARGE => 1600,
    ];

    public const QUALITY = 80;

    public static function sync(ContentHomeSlide $slide): void
    {
        foreach (['background_image', 'mobile_image'] as $field) {
            $current = $slide->getAttribute($field);
            $currentName = is_string($current) && $current !== '' ? $current : null;
            if ($currentName !== null) {
                self::convert($currentName);
            }
            if ($slide->wasChanged($field)) {
                $previous = $slide->getOriginal($field);
                if (is_string($previous) && $previous !== '' && $previous !== $currentName) {
                    self::deleteConversions($previous);
                }
            }
        }
    }

    public static function convert(?string $bareName): void
    {
        if ($bareName === null || $bareName === '') {
            return;
        }
        $bareName = basename($bareName);
        $disk = Storage::disk('public');
        $source = self::DIRECTORY.'/'.$bareName;
        if (! $disk->exists($source)) {
            return;
        }
        try {
            $manager = new ImageManager(['driver' => 'gd']);
            $absolute = $disk->path($source);
            foreach (self::WIDTHS as $name => $width) {
                $image = $manager->make($absolute);
                $image->widen($width, function (\Intervention\Image\Constraint $constraint): void {
                    $constraint->upsize();
                });
                $disk->put(self::conversionPath($bareName, $name), (string) $image->encode('webp', self::QUALITY));
            }
        } catch (Throwable $e) {
            CaughtExceptionLogger::error('HomeSlideImages: conversion failed', $e, ['file' => $bareName]);
        }
    }

    public static function deleteConversions(string $bareName): void
    {
        $bareName = basename($bareName);
        $disk = Storage::disk('public');
        foreach (array_keys(self::WIDTHS) as $name) {
            $path = self::conversionPath($bareName, $name);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Storefront URLs for one slide file: WebP srcset when conversions exist,
     * otherwise the original JPEG/PNG.
     *
     * @return Banner
     */
    public static function banner(?string $bareName): array
    {
        if ($bareName === null || $bareName === '') {
            return ['src' => '', 'srcset' => '', 'webp' => false];
        }
        $bareName = basename($bareName);
        $original = '/storage/'.self::DIRECTORY.'/'.$bareName;
        $web = self::urlIfExists($bareName, self::WEB);
        $large = self::urlIfExists($bareName, self::LARGE);
        if ($web === null && $large === null) {
            return ['src' => $original, 'srcset' => '', 'webp' => str_ends_with(strtolower($bareName), '.webp')];
        }
        $parts = [];
        if ($web !== null) {
            $parts[] = $web.' 800w';
        }
        if ($large !== null) {
            $parts[] = $large.' 1600w';
        }

        return [
            'src' => $web ?? $large, // one of the two exists: both missing returned above
            'srcset' => implode(', ', $parts),
            'webp' => true,
        ];
    }

    private static function urlIfExists(string $bareName, string $conversion): ?string
    {
        $path = self::conversionPath($bareName, $conversion);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.$path;
    }

    private static function conversionPath(string $bareName, string $conversion): string
    {
        return self::DIRECTORY.'/conversions/'.pathinfo($bareName, PATHINFO_FILENAME).'-'.$conversion.'.webp';
    }
}
