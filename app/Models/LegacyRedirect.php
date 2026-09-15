<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A stored redirect served for paths the router does not know
 * (App\Exceptions\Handler). Paths are stored normalised: leading slash,
 * no trailing slash, no query string.
 */
class LegacyRedirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'hits', 'last_hit_at'];

    protected $casts = ['status_code' => 'integer', 'hits' => 'integer', 'last_hit_at' => 'datetime'];

    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        return '/'.trim($path, '/');
    }

    /** Find the redirect for a request path, or null. */
    public static function for(string $path): ?self
    {
        return self::query()->where('from_path', self::normalizePath($path))->first();
    }

    /**
     * Record that an old path now lives at a new one. Never creates a chain:
     * redirects that pointed at the old path are rewritten to the new one,
     * and a redirect from the new path is removed.
     */
    public static function record(string $fromPath, string $toPath, int $statusCode = 301): self
    {
        $fromPath = self::normalizePath($fromPath);
        $toPath = self::normalizePath($toPath);
        if ($fromPath === $toPath) {
            self::query()->where('from_path', $fromPath)->delete();

            return new self(['from_path' => $fromPath, 'to_path' => $toPath, 'status_code' => $statusCode]);
        }

        self::query()->where('to_path', $fromPath)->update(['to_path' => $toPath]);
        self::query()->where('from_path', $toPath)->delete();

        return self::query()->updateOrCreate(['from_path' => $fromPath], ['to_path' => $toPath, 'status_code' => $statusCode]);
    }

    public function registerHit(): void
    {
        $this->forceFill(['hits' => $this->hits + 1, 'last_hit_at' => now()])->saveQuietly();
    }
}
