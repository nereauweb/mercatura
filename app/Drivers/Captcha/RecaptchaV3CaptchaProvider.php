<?php

declare(strict_types=1);

namespace App\Drivers\Captcha;

use App\Contracts\CaptchaProvider;
use Illuminate\Support\Facades\Log;
use TimeHunter\LaravelGoogleReCaptchaV3\GoogleReCaptchaV3;

/**
 * Google reCAPTCHA v3 through timehunter/laravel-google-recaptcha-v3
 * (keys and per-action thresholds in config/googlerecaptchav3.php).
 *
 * Google's script is not loaded with the page: the package's script tag is
 * replaced by a loader that fetches it on the first focus or pointer down
 * inside a form with a captcha field, or on the first token refresh before
 * a submit (every captcha form refreshes, see captcha-refresh.js). The
 * floating badge is hidden and each field carries Google's notice instead,
 * as Google's terms require.
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
        return (string) $this->service()->renderField($fieldId, $action)
            .'<p class="mt-1 text-xs text-text-muted">'.__('frontend.captcha.recaptcha_notice').'</p>';
    }

    public function renderScript(): string
    {
        $html = (string) $this->service()->init();
        $html = (string) preg_replace("#<script\\s+id='gReCaptchaScript'.*?</script>#s", '', $html, 1, $replaced);
        if ($replaced === 0) {
            // Unknown package markup: keep its own (eager) loading rather than break the forms.
            return $html.'<script>window.mercaturaCaptcha={refresh:function(f,a){return typeof refreshReCaptchaV3==="function"?refreshReCaptchaV3(f,a):Promise.resolve();}};</script>';
        }
        $src = config('googlerecaptchav3.api_js_url').'?'.http_build_query([
            'render' => config('googlerecaptchav3.inline') ? 'explicit' : config('googlerecaptchav3.site_key'),
            'hl' => config('googlerecaptchav3.language'),
            'onload' => 'mercaturaCaptchaOnload',
        ]);

        // onloadCallback (package) renders the widgets; refreshReCaptchaV3 (package) returns a fresh token.
        return $html.'<style>.grecaptcha-badge{visibility:hidden}</style><script>(function(){'
            .'var src='.json_encode($src, JSON_UNESCAPED_SLASHES).',ready=null;'
            .'function load(){if(ready)return ready;ready=new Promise(function(resolve){'
            .'window.mercaturaCaptchaOnload=function(){if(typeof onloadCallback==="function")onloadCallback();resolve();};'
            .'var s=document.createElement("script");s.id="gReCaptchaScript";s.src=src;s.async=true;s.onerror=function(){resolve();};document.head.appendChild(s);});return ready;}'
            .'function wake(e){var f=e.target&&e.target.closest?e.target.closest("form"):null;if(f&&f.querySelector("[data-captcha]"))load();}'
            .'document.addEventListener("focusin",wake);document.addEventListener("pointerdown",wake);'
            .'window.mercaturaCaptcha={load:load,refresh:function(f,a){return load().then(function(){return typeof refreshReCaptchaV3==="function"&&window["client"+f]!==undefined?refreshReCaptchaV3(f,a):undefined;});}};'
            .'})();</script>';
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
