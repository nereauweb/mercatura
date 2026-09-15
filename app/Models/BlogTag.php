<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    protected $table = 'blog_tags';

    protected $fillable = [
        'name',
        'slug',
        'position',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(BlogArticle::class, 'blog_article_tag', 'blog_tag_id', 'blog_article_id');
    }
}
