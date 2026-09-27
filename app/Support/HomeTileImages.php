<?php

declare(strict_types=1);

namespace App\Support;

/** Home tile photos: 420 and 840 px WebP sidecars in home_tiles/conversions (the tile box is 16:9, cropped at the centre). */
final class HomeTileImages extends ContentImages
{
    public const DIRECTORY = 'home_tiles';

    public const WIDTHS = ['web' => 420, 'large' => 840];

    public const FIELDS = ['image'];
}
