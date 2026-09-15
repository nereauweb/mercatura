<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AIEnrichmentProvider;
use App\Contracts\CaptchaProvider;
use App\Contracts\NewsletterProvider;
use App\Contracts\PersonalizationProvider;
use App\Contracts\SearchEngine;
use App\Contracts\TransactionalMailer;
use App\Drivers\AI\NullAIEnrichmentProvider;
use App\Drivers\Captcha\NullCaptchaProvider;
use App\Drivers\Captcha\RecaptchaV3CaptchaProvider;
use App\Drivers\Mail\BladeTransactionalMailer;
use App\Drivers\Mail\BrevoTransactionalMailer;
use App\Drivers\Mail\MandrillTransactionalMailer;
use App\Drivers\Newsletter\BrevoNewsletterProvider;
use App\Drivers\Newsletter\MailchimpNewsletterProvider;
use App\Drivers\Newsletter\NullNewsletterProvider;
use App\Drivers\Personalization\NullPersonalizationProvider;
use App\Drivers\Search\ScoutSearchEngine;
use App\Support\ImportConnectors;
use App\Support\MailTemplateCatalog;
use App\Support\PaymentGateways;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

/**
 * Binds every provider contract (docs/01_STACK_SPECIFICATION.md §4) to the
 * driver named in config('mercatura.providers.*'). Switching provider is a
 * config change: nothing outside app/Drivers references an SDK.
 */
final class DriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MailTemplateCatalog::class, fn () => new MailTemplateCatalog((array) config('mail-templates')));

        $this->app->bind(TransactionalMailer::class, function (Application $app) {
            $provider = $this->provider('mail');

            return match ($provider) {
                'brevo' => $app->make(BrevoTransactionalMailer::class),
                'mandrill' => MandrillTransactionalMailer::fromConfig($app->make(MailTemplateCatalog::class)),
                default => $this->bladeMailer($app, $provider),
            };
        });

        $this->app->bind(NewsletterProvider::class, function (Application $app) {
            $provider = $this->provider('newsletter');

            return match ($provider) {
                'brevo' => $app->make(BrevoNewsletterProvider::class),
                'mailchimp' => MailchimpNewsletterProvider::fromConfig(),
                'null' => new NullNewsletterProvider,
                default => throw new InvalidArgumentException("Unknown newsletter provider [{$provider}]."),
            };
        });

        $this->app->bind(CaptchaProvider::class, function () {
            $provider = $this->provider('captcha');

            return match ($provider) {
                'recaptcha' => new RecaptchaV3CaptchaProvider,
                'null' => new NullCaptchaProvider,
                default => throw new InvalidArgumentException("Unknown captcha provider [{$provider}]."),
            };
        });

        // Any Scout engine: the driver name is mirrored into scout.driver by MercaturaServiceProvider.
        $this->app->bind(SearchEngine::class, ScoutSearchEngine::class);

        $this->app->bind(AIEnrichmentProvider::class, function () {
            $provider = $this->provider('ai');

            return match ($provider) {
                'null' => new NullAIEnrichmentProvider,
                'anthropic' => throw new RuntimeException('The Anthropic AI enrichment driver is not implemented yet.'),
                default => throw new InvalidArgumentException("Unknown AI provider [{$provider}]."),
            };
        });

        $this->app->bind(PersonalizationProvider::class, function () {
            $provider = $this->provider('personalization');

            return match ($provider) {
                'null' => new NullPersonalizationProvider,
                default => throw new InvalidArgumentException("Unknown personalization provider [{$provider}]."),
            };
        });

        $this->app->singleton(PaymentGateways::class, fn (Application $app) => new PaymentGateways($app));

        // Connector packages register themselves here from their service providers.
        $this->app->singleton(ImportConnectors::class, fn () => new ImportConnectors);
        $this->app->singleton(\App\Support\Connectors\MarkupRules::class, fn (Application $app) => new \App\Support\Connectors\MarkupRules($app->make(ImportConnectors::class)));
    }

    private function provider(string $key): string
    {
        $value = config('mercatura.providers.'.$key);

        return is_string($value) && $value !== '' ? strtolower($value) : 'null';
    }

    /**
     * Every other mail provider name is a Laravel mailer (postmark, smtp, log,
     * array…) rendering the in-app Blade templates.
     */
    private function bladeMailer(Application $app, string $mailer): BladeTransactionalMailer
    {
        if ($mailer === 'default') {
            $mailer = (string) config('mail.default');
        }
        if (! is_array(config('mail.mailers.'.$mailer))) {
            throw new InvalidArgumentException("Unknown mail provider [{$mailer}]: not a driver and not a mailer in config/mail.php.");
        }

        return new BladeTransactionalMailer($app->make(MailTemplateCatalog::class), $app->make('mail.manager'), $mailer);
    }
}
