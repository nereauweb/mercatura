<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentHomeSlide extends Model
{
    use SoftDeletes;

    protected $table = 'content_home_slides';

    protected $fillable = [
        'position',
        'background_color',
        'background_image',
        'title_color',
        'title_text',
        'subtitle_color',
        'subtitle_text',
        'text_color',
        'text',
        'cta_text',
        'cta_link',
    ];
}
