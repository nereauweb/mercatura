<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate limits shared by every report entry point (docs/06_USER_REPORTS.md §4):
 * per user when logged in (admins included), per IP for guests, plus a daily
 * upload quota per user. Enforced in the actions, so no entry point skips them.
 */
final class ReportLimits
{
    /** Counts one attempt; throws when a window is already full. */
    public function hit(?User $user, string $ip): void
    {
        $limits = (array) config('mercatura.reports.limits');
        $windows = $user !== null
            ? ['reports:user-minute:'.$user->id => [(int) $limits['user_per_minute'], 60], 'reports:user-day:'.$user->id => [(int) $limits['user_per_day'], 86400]]
            : ['reports:ip-minute:'.$ip => [(int) $limits['guest_per_minute'], 60], 'reports:ip-day:'.$ip => [(int) $limits['guest_per_day'], 86400]];

        foreach ($windows as $key => [$max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new ThrottleRequestsException(__('frontend.reports.errors.too_many'));
            }
        }
        foreach ($windows as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /** Adds the uploaded bytes to the user's daily quota; throws when it would be exceeded. */
    public function upload(User $user, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $kb = (int) ceil($bytes / 1024);
        $key = 'reports:user-kb:'.$user->id;
        if (RateLimiter::attempts($key) + $kb > (int) config('mercatura.reports.limits.user_mb_per_day') * 1024) {
            throw new ThrottleRequestsException(__('frontend.reports.errors.quota'));
        }
        RateLimiter::increment($key, 86400, $kb);
    }
}
