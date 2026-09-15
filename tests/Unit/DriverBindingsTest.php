<?php

declare(strict_types=1);

namespace Tests\Unit;

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
use App\Drivers\Payment\PayPalPaymentGateway;
use App\Drivers\Payment\StripePaymentGateway;
use App\Drivers\Personalization\NullPersonalizationProvider;
use App\Drivers\Search\ScoutSearchEngine;
use App\Support\ImportConnectors;
use App\Support\PaymentGateways;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

/**
 * Switching a provider is a config change: every contract resolves to the
 * driver named in config('mercatura.providers.*') and nothing else.
 */
class DriverBindingsTest extends TestCase
{
    private function bladeMailer(): BladeTransactionalMailer
    {
        $mailer = $this->app->make(TransactionalMailer::class);
        $this->assertInstanceOf(BladeTransactionalMailer::class, $mailer);

        return $mailer;
    }

    public function test_test_environment_uses_local_drivers(): void
    {
        $this->assertSame('array', $this->bladeMailer()->mailer());
        $this->assertInstanceOf(NullNewsletterProvider::class, $this->app->make(NewsletterProvider::class));
        $this->assertInstanceOf(NullCaptchaProvider::class, $this->app->make(CaptchaProvider::class));
        $this->assertInstanceOf(NullAIEnrichmentProvider::class, $this->app->make(AIEnrichmentProvider::class));
        $this->assertInstanceOf(NullPersonalizationProvider::class, $this->app->make(PersonalizationProvider::class));
        $this->assertInstanceOf(ScoutSearchEngine::class, $this->app->make(SearchEngine::class));
        $this->assertSame('collection', $this->app->make(SearchEngine::class)->key());
    }

    public function test_mail_provider_selects_hosted_or_blade_drivers(): void
    {
        config(['mercatura.providers.mail' => 'brevo']);
        $this->assertInstanceOf(BrevoTransactionalMailer::class, $this->app->make(TransactionalMailer::class));

        config(['mercatura.providers.mail' => 'mandrill']);
        $this->assertInstanceOf(MandrillTransactionalMailer::class, $this->app->make(TransactionalMailer::class));

        config(['mercatura.providers.mail' => 'postmark']);
        $this->assertSame('postmark', $this->bladeMailer()->mailer());

        config(['mercatura.providers.mail' => 'default', 'mail.default' => 'log']);
        $this->assertSame('log', $this->bladeMailer()->mailer());

        config(['mercatura.providers.mail' => 'not-a-mailer']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(TransactionalMailer::class);
    }

    public function test_newsletter_provider_selects_driver(): void
    {
        config(['mercatura.providers.newsletter' => 'brevo']);
        $this->assertInstanceOf(BrevoNewsletterProvider::class, $this->app->make(NewsletterProvider::class));

        config(['mercatura.providers.newsletter' => 'mailchimp']);
        $this->assertInstanceOf(MailchimpNewsletterProvider::class, $this->app->make(NewsletterProvider::class));

        config(['mercatura.providers.newsletter' => 'nope']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(NewsletterProvider::class);
    }

    public function test_captcha_provider_selects_driver(): void
    {
        config(['mercatura.providers.captcha' => 'recaptcha']);
        $captcha = $this->app->make(CaptchaProvider::class);
        $this->assertInstanceOf(RecaptchaV3CaptchaProvider::class, $captcha);
        $this->assertSame('g-recaptcha-response', $captcha->responseField());

        config(['mercatura.providers.captcha' => 'nope']);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CaptchaProvider::class);
    }

    public function test_ai_anthropic_is_reserved_until_implemented(): void
    {
        config(['mercatura.providers.ai' => 'anthropic']);
        $this->expectException(RuntimeException::class);
        $this->app->make(AIEnrichmentProvider::class);
    }

    public function test_search_provider_is_mirrored_into_scout_driver(): void
    {
        $this->assertSame(config('mercatura.providers.search'), config('scout.driver'));
    }

    public function test_payment_gateways_come_from_config(): void
    {
        $gateways = $this->app->make(PaymentGateways::class);

        $this->assertInstanceOf(StripePaymentGateway::class, $gateways->for('stripe'));
        $this->assertInstanceOf(PayPalPaymentGateway::class, $gateways->for('paypal'));
        $this->assertFalse($gateways->has('bank_transfer'));
        $this->assertSame(['bank_transfer', 'stripe', 'paypal'], $gateways->enabledMethods());

        $this->expectException(InvalidArgumentException::class);
        $gateways->for('bank_transfer');
    }

    public function test_import_connectors_registry_is_bound_and_flag_driven(): void
    {
        $connectors = $this->app->make(ImportConnectors::class);

        $this->assertSame($connectors, $this->app->make(ImportConnectors::class));
        foreach ($connectors->all() as $connector) {
            $this->assertFalse($connector->enabled(), $connector->key().' must be off unless MERCATURA_CONNECTOR_* is set');
        }
        $this->assertSame([], $connectors->enabled());
    }
}
