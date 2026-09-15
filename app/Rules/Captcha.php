<?php

declare(strict_types=1);

namespace App\Rules;

use App\Contracts\CaptchaProvider;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates the captcha token of a public form through the configured
 * CaptchaProvider. Implicit: it runs even when the field is absent, so a
 * request without a token fails unless the provider accepts everything.
 *
 * Usage: $request->validate([...Captcha::rules('login'), 'email' => ...]);
 */
final class Captcha implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly string $action) {}

    /**
     * Rule set keyed by the provider's response field.
     *
     * @return array<string, array<int, self>>
     */
    public static function rules(string $action): array
    {
        return [self::field() => [new self($action)]];
    }

    /** Request input name carrying the token (provider-specific). */
    public static function field(): string
    {
        return app(CaptchaProvider::class)->responseField();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $provider = app(CaptchaProvider::class);
        if ($provider->verify($this->action, $value, request()->getClientIp())) {
            return;
        }

        $fail('validation.custom.captcha.failed')->translate();
    }
}
