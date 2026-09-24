<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\HomeSlideImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentHomeSlide extends Model
{
    use SoftDeletes;

    protected $table = 'content_home_slides';

    protected static function booted(): void
    {
        static::saved(function (self $slide): void {
            HomeSlideImages::sync($slide);
        });
    }

    protected $fillable = [
        'position',
        'active',
        'background_color',
        'background_image',
        'mobile_image',
        'title_color',
        'title_text',
        'subtitle_color',
        'subtitle_text',
        'text_color',
        'text',
        'cta_text',
        'cta_link',
    ];

    protected $casts = ['active' => 'boolean'];
}
