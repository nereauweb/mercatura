<?php

namespace App\Models;

use App\Models\Concerns\HasSeoFields;
use App\Models\Concerns\RedirectsOldSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogArticle extends Model
{
    use HasSeoFields;
    use RedirectsOldSlugs;

    protected $casts = ['active' => 'boolean', 'navbar' => 'boolean', 'noindex' => 'boolean', 'position' => 'integer'];

    public static function pathForSlug(string $slug): string
    {
        return '/blog/'.$slug;
    }

    protected $table = 'blog_articles';

    protected $fillable = [
        'title',
        'text',
        'excerpt',
        'cover',
        'slug',
        'active',
        'navbar',
        'position',
        'seo_title',
        'seo_description',
        'canonical_url',
        'noindex',
        'og_title',
        'og_description',
        'og_image',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_article_tag', 'blog_article_id', 'blog_tag_id');
    }
}
