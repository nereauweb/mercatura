<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Home slideshow files: web 800 and large 1600 WebP sidecars in home_slides/conversions.
 * Desktop banners are 1920×420 (cropped at the centre), phone images 800×800.
 */
final class HomeSlideImages extends ContentImages
{
    public const DIRECTORY = 'home_slides';

    public const WEB = 'web';

    public const LARGE = 'large';

    public const WIDTHS = [self::WEB => 800, self::LARGE => 1600];

    public const FIELDS = ['background_image', 'mobile_image'];
}
