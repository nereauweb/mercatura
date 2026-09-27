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
    protected $fillable = ['from_path', 'to_path', 'status_code', 'keep_query', 'hits', 'last_hit_at'];

    protected $casts = ['status_code' => 'integer', 'keep_query' => 'boolean', 'hits' => 'integer', 'last_hit_at' => 'datetime'];

    /**
     * The URL to send the visitor to: `{name}` placeholders in the target take the
     * value of that query parameter (`/search/{q}` for `?q=penne`), and a redirect
     * flagged keep_query forwards the remaining query string (`/prodotti?brand=X`).
     *
     * @param  array<string, mixed>  $query  the request's query parameters
     */
    public function targetFor(array $query = []): ?string
    {
        if ($this->to_path === null) {
            return null;
        }
        $used = [];
        $target = (string) preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($query, &$used): string {
            $used[] = $m[1];
            $value = $query[$m[1]] ?? '';

            return rawurlencode(is_scalar($value) ? (string) $value : '');
        }, $this->to_path);
        if ($this->keep_query) {
            $rest = array_diff_key($query, array_flip($used));
            if ($rest !== []) {
                $target .= (str_contains($target, '?') ? '&' : '?').http_build_query($rest);
            }
        }

        return $target;
    }

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
    public static function record(string $fromPath, string $toPath, int $statusCode = 301, bool $keepQuery = false): self
    {
        $fromPath = self::normalizePath($fromPath);
        $toPath = self::normalizeTarget($toPath);
        if ($fromPath === $toPath) {
            self::query()->where('from_path', $fromPath)->delete();

            return new self(['from_path' => $fromPath, 'to_path' => $toPath, 'status_code' => $statusCode, 'keep_query' => $keepQuery]);
        }

        self::query()->where('to_path', $fromPath)->update(['to_path' => $toPath]);
        self::query()->where('from_path', $toPath)->delete();

        return self::query()->updateOrCreate(['from_path' => $fromPath], ['to_path' => $toPath, 'status_code' => $statusCode, 'keep_query' => $keepQuery]);
    }

    /** A target keeps its query string and placeholders (only the path part is normalised); absolute URLs pass through. */
    public static function normalizeTarget(string $target): string
    {
        if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
            return $target;
        }
        [$path, $query] = array_pad(explode('?', $target, 2), 2, null);

        return self::normalizePath((string) $path).($query !== null && $query !== '' ? '?'.$query : '');
    }

    /**
     * Rows written in bulk (initial load of a large map): no chain rewriting, existing
     * rows with the same from_path are replaced.
     *
     * @param  list<array{from_path: string, to_path: string|null, status_code: int, keep_query: bool}>  $rows
     */
    public static function upsertMany(array $rows): int
    {
        $now = now();
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[self::normalizePath($row['from_path'])] = [
                'from_path' => self::normalizePath($row['from_path']),
                'to_path' => $row['to_path'] === null ? null : self::normalizeTarget($row['to_path']),
                'status_code' => $row['status_code'],
                'keep_query' => $row['keep_query'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($prepared === []) {
            return 0;
        }
        self::query()->upsert(array_values($prepared), ['from_path'], ['to_path', 'status_code', 'keep_query', 'updated_at']);

        return count($prepared);
    }

    public function registerHit(): void
    {
        $this->forceFill(['hits' => $this->hits + 1, 'last_hit_at' => now()])->saveQuietly();
    }
}
