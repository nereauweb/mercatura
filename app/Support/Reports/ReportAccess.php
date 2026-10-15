<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Contracts\CaptchaProvider;
use App\Models\User;

/**
 * Who may send a report to the admin (docs/06_USER_REPORTS.md §2.3, §4).
 * `public` needs a working captcha provider: without one it behaves as `auth`.
 * Admins never report here, they go through the message forwarder.
 */
final class ReportAccess
{
    public const OFF = 'off';

    public const AUTH = 'auth';

    public const PUBLIC = 'public';

    /** The configured mode, after the captcha fallback. */
    public function effective(): string
    {
        $mode = (string) config('mercatura.reports.access', self::AUTH);
        if (! in_array($mode, [self::OFF, self::AUTH, self::PUBLIC], true)) {
            return self::OFF;
        }

        return $mode === self::PUBLIC && $this->captchaMissing() ? self::AUTH : $mode;
    }

    /** True when `public` is configured but runs as `auth` for lack of a captcha (shown in the panel). */
    public function degraded(): bool
    {
        return config('mercatura.reports.access') === self::PUBLIC && $this->captchaMissing();
    }

    public function allows(?User $user): bool
    {
        return match (true) {
            $this->effective() === self::OFF => false,
            $user === null => $this->effective() === self::PUBLIC,
            default => ! $user->hasRole('admin'),
        };
    }

    /** Guests never attach files, whatever the mode. */
    public function canAttach(?User $user): bool
    {
        return $user !== null && $this->allows($user);
    }

    /** No provider, or one without keys (reCAPTCHA with an empty site key). */
    private function captchaMissing(): bool
    {
        return ! app(CaptchaProvider::class)->enabled();
    }
}
