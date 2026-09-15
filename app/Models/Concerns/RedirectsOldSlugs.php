<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\LegacyRedirect;

/**
 * When the slug of a published entity changes, the old URL keeps working:
 * a legacy_redirects row is recorded (301) so links and search results do
 * not break. The model provides the URL path for a slug.
 */
trait RedirectsOldSlugs
{
    abstract public static function pathForSlug(string $slug): string;

    public static function bootRedirectsOldSlugs(): void
    {
        static::updating(function (self $model): void {
            if (! $model->isDirty('slug')) {
                return;
            }
            $old = (string) $model->getOriginal('slug');
            $new = (string) $model->slug;
            if ($old === '' || $new === '' || $old === $new) {
                return;
            }
            LegacyRedirect::record(static::pathForSlug($old), static::pathForSlug($new));
        });
    }
}
