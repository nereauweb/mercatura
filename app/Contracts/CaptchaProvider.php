<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Bot protection on public forms. Selected by config('mercatura.providers.captcha').
 * Views render fields and scripts through this contract, the App\Rules\Captcha
 * rule verifies through it; the "null" driver renders nothing and always passes.
 */
interface CaptchaProvider
{
    /** Driver name, e.g. "recaptcha" or "null". */
    public function key(): string;

    /** False when no verification happens (null driver, missing keys). */
    public function enabled(): bool;

    /** Request input name that carries the token to verify. */
    public function responseField(): string;

    /** Markup for one form's captcha field (may be empty). */
    public function renderField(string $fieldId, string $action): string;

    /**
     * Scripts to emit once per page after all fields. When the driver needs a
     * fresh token before submit it must define window.mercaturaCaptcha.refresh(fieldId, action)
     * returning a Promise; resources/js/storefront/captcha-refresh.js calls it.
     */
    public function renderScript(): string;

    /** Verify a submitted token for the given action. */
    public function verify(string $action, mixed $token, ?string $ip = null): bool;
}
