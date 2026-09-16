<?php

declare(strict_types=1);

namespace App\Drivers\Captcha;

use App\Contracts\CaptchaProvider;
use Illuminate\Support\Facades\Log;
use TimeHunter\LaravelGoogleReCaptchaV3\GoogleReCaptchaV3;

/**
 * Google reCAPTCHA v3 through timehunter/laravel-google-recaptcha-v3
 * (keys and per-action thresholds in config/googlerecaptchav3.php).
 */
final class RecaptchaV3CaptchaProvider implements CaptchaProvider
{
    private function service(): GoogleReCaptchaV3
    {
        return app('GoogleReCaptchaV3');
    }

    public function key(): string
    {
        return 'recaptcha';
    }

    public function enabled(): bool
    {
        return (bool) config('googlerecaptchav3.is_service_enabled', true)
            && (string) config('googlerecaptchav3.site_key') !== '';
    }

    public function responseField(): string
    {
        return 'g-recaptcha-response';
    }

    public function renderField(string $fieldId, string $action): string
    {
        return (string) $this->service()->renderField($fieldId, $action);
    }

    public function renderScript(): string
    {
        // The package defines refreshReCaptchaV3(fieldId, action); expose it
        // under the provider-neutral hook used by captcha-refresh.js.
        return (string) $this->service()->init()
            .'<script>window.mercaturaCaptcha={refresh:function(f,a){return typeof refreshReCaptchaV3==="function"?refreshReCaptchaV3(f,a):Promise.resolve();}};</script>';
    }

    public function verify(string $action, mixed $token, ?string $ip = null): bool
    {
        // No site key: nothing was rendered, nothing to verify (an installation without keys is a captcha switched off).
        if (! $this->enabled()) {
            return true;
        }
        $response = $this->service()->setAction($action)->verifyResponse(is_string($token) ? $token : '', $ip);
        if ($response->isSuccess()) {
            return true;
        }

        Log::info('reCAPTCHA v3 verification failed', [
            'action' => $action,
            'message' => (string) $response->getMessage(),
            'has_token' => filled($token),
            'ip' => $ip,
        ]);

        return false;
    }
}
