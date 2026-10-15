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

    /**
     * Rewrites a user-uploaded image from its pixels only (JPEG, PNG, WebP):
     * metadata (EXIF, GPS, text chunks) is dropped and anything appended to or
     * hidden in the file does not survive. False when the file is not one of
     * those images or GD cannot decode it: the caller must then reject it.
     */
    public static function reencode(string $path): bool
    {
        $info = @getimagesize($path);
        if (! $info) {
            return false;
        }

        return match ($info[2]) {
            IMAGETYPE_JPEG => self::reencodeJpeg($path),
            IMAGETYPE_PNG => self::reencodeWith($path, 'imagecreatefrompng', static fn (\GdImage $image): bool => imagepng($image, $path, 6)),
            IMAGETYPE_WEBP => self::reencodeWith($path, 'imagecreatefromwebp', static fn (\GdImage $image): bool => imagewebp($image, $path, 90)),
            default => false,
        };
    }

    /** @param  callable(\GdImage): bool  $write */
    private static function reencodeWith(string $path, string $decoder, callable $write): bool
    {
        if (! function_exists($decoder)) {
            return false;
        }
        $image = @$decoder($path);
        if (! $image instanceof \GdImage) {
            return false;
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $ok = $write($image);
        imagedestroy($image);

        return $ok;
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
