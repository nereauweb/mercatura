<?php

declare(strict_types=1);

namespace App\Support;

use enshrined\svgSanitize\Sanitizer as SvgSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/** The artwork files a customer uploads for a customization: accepted types, safe names, SVG sanitising. Shared by the order page and the configurator. */
final class ArtworkFiles
{
    public const MIMETYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/svg+xml', 'image/svg',
        'application/pdf', 'application/postscript', 'application/illustrator',
        'application/octet-stream', // AI/EPS are sometimes reported as octet-stream by the client
    ];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'svg', 'pdf', 'ai', 'eps'];

    public const MAX_KB = 20480;

    /** @return list<string> Laravel validation rules for an artwork upload. */
    public static function rules(): array
    {
        return ['required', 'file', 'min:1', 'max:'.self::MAX_KB, 'mimetypes:'.implode(',', self::MIMETYPES), 'mimes:'.implode(',', self::EXTENSIONS)];
    }

    public static function extension(UploadedFile $file): string
    {
        return strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'bin');
    }

    public static function isSvg(string $extension): bool
    {
        return in_array(strtolower($extension), ['svg', 'svgz'], true);
    }

    public static function safeName(string $prefix, string $extension): string
    {
        return $prefix.'_'.now()->timestamp.'_'.Str::random(8).'.'.(in_array($extension, self::EXTENSIONS, true) ? $extension : 'bin');
    }

    /** Sanitised SVG contents (scripts, handlers and remote references removed), or null when the file cannot be made safe. */
    public static function sanitizeSvg(string $contents): ?string
    {
        $sanitizer = new SvgSanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($contents);

        return $clean === false ? null : $clean;
    }

    /**
     * Store an upload on a disk under a directory, sanitising SVGs; null when an SVG is not acceptable.
     *
     * @return string|null the stored path
     */
    public static function store(UploadedFile $file, string $disk, string $directory, string $prefix): ?string
    {
        $extension = self::extension($file);
        if (! in_array($extension, self::EXTENSIONS, true)) {
            return null;
        }
        $name = self::safeName($prefix, $extension);
        if (self::isSvg($extension)) {
            $clean = self::sanitizeSvg((string) file_get_contents($file->getRealPath()));
            if ($clean === null) {
                return null;
            }
            \Illuminate\Support\Facades\Storage::disk($disk)->put($directory.'/'.$name, $clean);

            return $directory.'/'.$name;
        }

        return $file->storeAs($directory, $name, $disk) ?: null;
    }
}
