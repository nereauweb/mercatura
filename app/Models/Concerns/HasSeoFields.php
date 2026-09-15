<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Columns added by the SEO fields migration (docs/ARCHITECTURE.md §12):
 * seo_title, seo_description, canonical_url, noindex, og_title,
 * og_description, og_image. Helpers resolve them with the given fallbacks
 * so views stay one-liners.
 */
trait HasSeoFields
{
    public function resolvedCanonicalUrl(string $default): string
    {
        $stored = trim((string) ($this->canonical_url ?? ''));

        return $stored !== '' ? $stored : $default;
    }

    public function isNoindex(): bool
    {
        return (bool) ($this->noindex ?? false);
    }

    public function resolvedOgTitle(string $default): string
    {
        $stored = trim((string) ($this->og_title ?? ''));

        return $stored !== '' ? $stored : $default;
    }

    public function resolvedOgDescription(string $default): string
    {
        $stored = trim((string) ($this->og_description ?? ''));

        return $stored !== '' ? $stored : $default;
    }

    public function resolvedOgImage(?string $default): ?string
    {
        $stored = trim((string) ($this->og_image ?? ''));

        return $stored !== '' ? $stored : $default;
    }
}
