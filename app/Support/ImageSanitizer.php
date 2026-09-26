<?php

declare(strict_types=1);

namespace App\Support;

use finfo;

/**
 * Some supplier JPEGs carry EXIF blocks that libmagic cannot parse: PHP's
 * finfo throws "Failed identify data" and the media library then never
 * generates the conversions, so the storefront falls back to the multi-MB
 * original. Re-encoding the file with GD (orientation applied) drops the
 * metadata and makes it readable again; readable files are left untouched.
 */
final class ImageSanitizer
{
    /** True when finfo identifies the file; false when it throws or gives nothing. */
    public static function readable(string $path): bool
    {
        try {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        } catch (\Throwable) {
            return false;
        }

        return is_string($mime) && $mime !== '';
    }

    /** Re-encodes the JPEG in place when finfo cannot read it. Returns true when the file was rewritten. */
    public static function prepare(string $path): bool
    {
        if (! is_file($path) || self::readable($path)) {
            return false;
        }

        return self::reencodeJpeg($path);
    }

    /** JPEG re-encoded by GD without metadata, EXIF orientation applied; other types are left alone. */
    public static function reencodeJpeg(string $path, int $quality = 92): bool
    {
        $info = @getimagesize($path);
        if (! $info || $info[2] !== IMAGETYPE_JPEG || ! function_exists('imagecreatefromjpeg')) {
            return false;
        }
        $image = @imagecreatefromjpeg($path);
        if ($image === false) {
            return false;
        }
        $orientation = function_exists('exif_read_data') ? (int) ((@exif_read_data($path) ?: [])['Orientation'] ?? 1) : 1;
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
        $ok = $rotated !== false && imagejpeg($rotated, $path, $quality);
        imagedestroy($image);
        if ($rotated !== false && $rotated !== $image) {
            imagedestroy($rotated);
        }

        return (bool) $ok;
    }
}
