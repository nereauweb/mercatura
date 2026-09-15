<?php

namespace App\Support;

class CanonicalUrl
{
    public static function absolute(string $path = '/'): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $host = preg_replace('/^www\./', '', $host);
        $path = '/'.ltrim($path, '/');

        return $path === '/'
            ? "https://{$host}"
            : 'https://'.$host.rtrim($path, '/');
    }
}
