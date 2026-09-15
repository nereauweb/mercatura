<?php

declare(strict_types=1);

namespace App\Drivers\Captcha;

use App\Contracts\CaptchaProvider;

/** No captcha: renders nothing and accepts every submission. For tests and local installs. */
final class NullCaptchaProvider implements CaptchaProvider
{
    public function key(): string
    {
        return 'null';
    }

    public function enabled(): bool
    {
        return false;
    }

    public function responseField(): string
    {
        return 'captcha_token';
    }

    public function renderField(string $fieldId, string $action): string
    {
        return '';
    }

    public function renderScript(): string
    {
        return '';
    }

    public function verify(string $action, mixed $token, ?string $ip = null): bool
    {
        return true;
    }
}
