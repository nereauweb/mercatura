<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staging switch (config mercatura.staging, MERCATURA_STAGING=true): every
 * response tells crawlers to stay away and, when a user and a password are
 * set, the whole site sits behind HTTP basic auth. Webhooks and the health
 * check stay reachable (mercatura.staging.except).
 */
final class StagingGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('mercatura.staging.enabled')) {
            return $next($request);
        }

        $user = (string) config('mercatura.staging.user');
        $password = (string) config('mercatura.staging.password');
        if ($user !== '' && $password !== '' && ! $request->is(...(array) config('mercatura.staging.except', []))) {
            if (! hash_equals($user, (string) $request->getUser()) || ! hash_equals($password, (string) $request->getPassword())) {
                return response('Staging: authentication required.', 401, [
                    'WWW-Authenticate' => 'Basic realm="Staging", charset="UTF-8"',
                    'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                ]);
            }
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
