<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Named WebP sidecars for content files on the public disk (home slides, home
 * tiles): a bare file name in DIRECTORY, conversions in DIRECTORY/conversions
 * at the WIDTHS of the subclass, quality 80, generated when the row is saved.
 *
 * @phpstan-type Banner array{src: string, srcset: string, webp: bool}
 */
abstract class ContentImages
{
    public const DIRECTORY = 'content';

    /** @var array<string, int> conversion name => width */
    public const WIDTHS = [];

    /** @var list<string> the model attributes holding a file name */
    public const FIELDS = [];

    public const QUALITY = 80;

    public static function sync(Model $row): void
    {
        foreach (static::FIELDS as $field) {
            $current = $row->getAttribute($field);
            $currentName = is_string($current) && $current !== '' ? $current : null;
            if ($currentName !== null) {
                static::convert($currentName);
            }
            if ($row->wasChanged($field)) {
                $previous = $row->getOriginal($field);
                if (is_string($previous) && $previous !== '' && $previous !== $currentName) {
                    static::deleteConversions($previous);
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
        $source = static::DIRECTORY.'/'.$bareName;
        if (! $disk->exists($source)) {
            return;
        }
        try {
            $manager = new ImageManager(['driver' => 'gd']);
            $absolute = $disk->path($source);
            foreach (static::WIDTHS as $name => $width) {
                $image = $manager->make($absolute);
                $image->widen($width, function (\Intervention\Image\Constraint $constraint): void {
                    $constraint->upsize();
                });
                $disk->put(static::conversionPath($bareName, $name), (string) $image->encode('webp', static::QUALITY));
            }
        } catch (Throwable $e) {
            CaughtExceptionLogger::error(static::class.': conversion failed', $e, ['file' => $bareName]);
        }
    }

    public static function deleteConversions(string $bareName): void
    {
        $bareName = basename($bareName);
        $disk = Storage::disk('public');
        foreach (array_keys(static::WIDTHS) as $name) {
            $path = static::conversionPath($bareName, $name);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    /**
     * Storefront URLs for one file: WebP srcset when conversions exist, otherwise the original.
     *
     * @return Banner
     */
    public static function banner(?string $bareName): array
    {
        if ($bareName === null || $bareName === '') {
            return ['src' => '', 'srcset' => '', 'webp' => false];
        }
        $bareName = basename($bareName);
        $original = '/storage/'.static::DIRECTORY.'/'.$bareName;
        $parts = [];
        $src = null;
        foreach (static::WIDTHS as $name => $width) {
            $url = static::urlIfExists($bareName, $name);
            if ($url !== null) {
                $parts[] = $url.' '.$width.'w';
                $src ??= $url;
            }
        }
        if ($src === null) {
            return ['src' => $original, 'srcset' => '', 'webp' => str_ends_with(strtolower($bareName), '.webp')];
        }

        return ['src' => $src, 'srcset' => implode(', ', $parts), 'webp' => true];
    }

    protected static function urlIfExists(string $bareName, string $conversion): ?string
    {
        $path = static::conversionPath($bareName, $conversion);
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.$path;
    }

    protected static function conversionPath(string $bareName, string $conversion): string
    {
        return static::DIRECTORY.'/conversions/'.pathinfo($bareName, PATHINFO_FILENAME).'-'.$conversion.'.webp';
    }
}
