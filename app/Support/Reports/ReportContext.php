<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Technical context of a report, resolved on the server: the reported page
 * (kept only when it belongs to this site), its route and area, the user
 * agent; viewport and recent JS errors come from the client, already validated.
 */
final class ReportContext
{
    /** The page URL only when it belongs to this site; anything else is dropped. */
    public static function sameSiteUrl(mixed $url, Request $request): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) === $request->getHost() ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $client  viewport, js_errors
     * @return array<string, mixed>
     */
    public static function build(?string $pageUrl, Request $request, array $client = []): array
    {
        $route = null;
        if ($pageUrl !== null) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($pageUrl))->getName();
            } catch (\Throwable) {
                $route = null;
            }
        }

        return array_filter([
            'area' => self::area($route),
            'route' => $route,
            'viewport' => $client['viewport'] ?? null,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'js_errors' => array_values((array) ($client['js_errors'] ?? [])) ?: null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /** admin | account | storefront, from the route name of the reported page. */
    public static function area(?string $route): string
    {
        return match (true) {
            $route === null => 'storefront',
            str_starts_with($route, 'filament.') => 'admin',
            str_starts_with($route, 'frontend.auth.'), str_starts_with($route, 'frontend.contacts.list'), str_starts_with($route, 'frontend.contacts.show'),
            str_starts_with($route, 'frontend.quotation.index'), str_starts_with($route, 'customer.') => 'account',
            default => 'storefront',
        };
    }
}
