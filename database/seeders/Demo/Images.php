<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use GdImage;
use RuntimeException;

/**
 * Generates the demo pictures with GD at seed time (nothing binary in the
 * repo): flat product shots, category icons and banners.
 */
final class Images
{
    /** Product shot: a shape in the variant colour on a light ground, SKU in the corner. */
    public static function product(string $hex, string $shape, string $label, int $size = 600): string
    {
        $img = self::canvas($size, $size, 'F3F4F6');
        $fill = self::color($img, $hex);
        $shadow = self::color($img, 'D1D5DB');
        $c = (int) ($size / 2);
        $r = (int) ($size * 0.32);
        switch ($shape) {
            case 'shirt':
                imagefilledpolygon($img, [$c - $r, $c - $r + 40, $c - (int) ($r * 0.5), $c - $r, $c - 40, $c - $r, $c, $c - $r + 50, $c + 40, $c - $r, $c + (int) ($r * 0.5), $c - $r, $c + $r, $c - $r + 40, $c + (int) ($r * 0.7), $c - (int) ($r * 0.4), $c + (int) ($r * 0.55), $c - (int) ($r * 0.5), $c + (int) ($r * 0.55), $c + $r, $c - (int) ($r * 0.55), $c + $r, $c - (int) ($r * 0.55), $c - (int) ($r * 0.5), $c - (int) ($r * 0.7), $c - (int) ($r * 0.4)], $fill);
                break;
            case 'bag':
                imagefilledrectangle($img, $c - $r, $c - (int) ($r * 0.5), $c + $r, $c + $r, $fill);
                imagesetthickness($img, 14);
                imagearc($img, $c, $c - (int) ($r * 0.5), $r, $r, 180, 360, $fill);
                break;
            case 'bottle':
                imagefilledrectangle($img, $c - (int) ($r * 0.4), $c - (int) ($r * 0.6), $c + (int) ($r * 0.4), $c + $r, $fill);
                imagefilledrectangle($img, $c - (int) ($r * 0.2), $c - $r, $c + (int) ($r * 0.2), $c - (int) ($r * 0.6), $shadow);
                break;
            case 'mug':
                imagefilledrectangle($img, $c - (int) ($r * 0.6), $c - (int) ($r * 0.6), $c + (int) ($r * 0.4), $c + (int) ($r * 0.6), $fill);
                imagesetthickness($img, 16);
                imagearc($img, $c + (int) ($r * 0.4), $c, (int) ($r * 0.7), (int) ($r * 0.7), 270, 90, $fill);
                break;
            case 'pen':
                imagefilledpolygon($img, [$c - (int) ($r * 0.12), $c - $r, $c + (int) ($r * 0.12), $c - $r, $c + (int) ($r * 0.12), $c + (int) ($r * 0.7), $c, $c + $r, $c - (int) ($r * 0.12), $c + (int) ($r * 0.7)], $fill);
                break;
            case 'book':
                imagefilledrectangle($img, $c - (int) ($r * 0.7), $c - $r, $c + (int) ($r * 0.7), $c + $r, $fill);
                imagefilledrectangle($img, $c - (int) ($r * 0.7), $c - $r, $c - (int) ($r * 0.55), $c + $r, $shadow);
                break;
            case 'device':
                imagefilledrectangle($img, $c - $r, $c - (int) ($r * 0.5), $c + $r, $c + (int) ($r * 0.5), $fill);
                imagefilledellipse($img, $c + (int) ($r * 0.7), $c, (int) ($r * 0.25), (int) ($r * 0.25), $shadow);
                break;
            case 'umbrella':
                imagefilledarc($img, $c, $c, 2 * $r, 2 * $r, 180, 360, $fill, IMG_ARC_PIE);
                imagefilledrectangle($img, $c - 8, $c, $c + 8, $c + $r, $shadow);
                break;
            case 'cap':
                imagefilledarc($img, $c, $c + (int) ($r * 0.2), 2 * $r, 2 * $r, 180, 360, $fill, IMG_ARC_PIE);
                imagefilledrectangle($img, $c - $r, $c + (int) ($r * 0.2), $c + (int) ($r * 1.3), $c + (int) ($r * 0.45), $fill);
                break;
            default:
                imagefilledellipse($img, $c, $c, 2 * $r, 2 * $r, $fill);
        }
        imagestring($img, 3, 16, $size - 26, $label, self::color($img, '6B7280'));

        return self::jpeg($img);
    }

    /** Category icon: coloured disc with the first letter. */
    public static function icon(string $hex, string $letter, int $size = 96): string
    {
        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledellipse($img, (int) ($size / 2), (int) ($size / 2), $size - 4, $size - 4, self::color($img, $hex));
        $font = 5;
        $x = (int) (($size - imagefontwidth($font)) / 2);
        $y = (int) (($size - imagefontheight($font)) / 2);
        imagestring($img, $font, $x, $y, $letter, self::color($img, 'FFFFFF'));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /** Banner: horizontal gradient with a few decorative discs. */
    public static function banner(int $width, int $height, string $from, string $to): string
    {
        $img = imagecreatetruecolor($width, $height);
        [$r1, $g1, $b1] = self::rgb($from);
        [$r2, $g2, $b2] = self::rgb($to);
        for ($x = 0; $x < $width; $x++) {
            $t = $x / max(1, $width - 1);
            $color = imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($img, $x, 0, $x, $height, $color);
        }
        $disc = imagecolorallocatealpha($img, 255, 255, 255, 96);
        foreach ([[0.82, 0.3, 0.9], [0.7, 0.85, 0.5], [0.92, 0.75, 0.35]] as [$fx, $fy, $fr]) {
            imagefilledellipse($img, (int) ($width * $fx), (int) ($height * $fy), (int) ($height * $fr), (int) ($height * $fr), $disc);
        }

        return self::jpeg($img);
    }

    private static function canvas(int $w, int $h, string $hex): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, self::color($img, $hex));

        return $img;
    }

    private static function color(GdImage $img, string $hex): int
    {
        [$r, $g, $b] = self::rgb($hex);
        $color = imagecolorallocate($img, $r, $g, $b);
        if ($color === false) {
            throw new RuntimeException('GD colour allocation failed');
        }

        return $color;
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $hex = explode('/', $hex)[0];

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function jpeg(GdImage $img): string
    {
        ob_start();
        imagejpeg($img, null, 82);

        return (string) ob_get_clean();
    }
}
