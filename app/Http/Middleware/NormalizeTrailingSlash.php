<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LegacyRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The trailing-slash policy in one place (docs/ARCHITECTURE.md SEO rules):
 * `/path/` is redirected once to `/path`, query string kept. When the path is
 * a legacy one with a stored redirect, the visitor goes straight to the final
 * target instead of through the slash-less URL first (no chains). Replaces
 * the Apache rule that used to do the redirect before the application ran.
 */
class NormalizeTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($path === '/' || ! str_ends_with($path, '/') || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }
        $trimmed = rtrim($path, '/');
        try {
            $legacy = LegacyRedirect::for($trimmed);
        } catch (\Throwable) {
            $legacy = null;
        }
        if ($legacy !== null) {
            $legacy->registerHit();
            $target = $legacy->targetFor($request->query->all());
            if ($legacy->status_code === 410 || $target === null) {
                return response()->view('frontend.pages.not_found', [], 410);
            }

            return redirect($target, $legacy->status_code === 302 ? 302 : 301);
        }
        $query = $request->getQueryString();

        return redirect($trimmed.($query !== null && $query !== '' ? '?'.$query : ''), 301);
    }
}
