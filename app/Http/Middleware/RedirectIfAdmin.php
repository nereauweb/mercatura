<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAdmin
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && Auth::user()->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        return $next($request);
    }
}
