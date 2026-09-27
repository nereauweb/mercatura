<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\HomeTileImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A photo block of the home (title, photo, link), managed in the admin independently of the categories. */
class ContentHomeTile extends Model
{
    use SoftDeletes;

    protected $table = 'content_home_tiles';

    protected static function booted(): void
    {
        static::saved(function (self $tile): void {
            HomeTileImages::sync($tile);
        });
    }

    protected $fillable = ['position', 'active', 'title', 'text', 'image', 'link'];

    protected $casts = ['active' => 'boolean'];
}
