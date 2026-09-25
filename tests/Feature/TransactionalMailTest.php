<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TransactionalMailer;
use App\Drivers\Mail\BladeTransactionalMailer;
use App\Support\MailSampleData;
use App\Support\MailTemplateCatalog;
use Illuminate\Mail\Transport\ArrayTransport;
use Tests\Support\LegacyContent;
use Tests\TestCase;

/** Every logical template renders through the Blade driver with neutral copy. */
class TransactionalMailTest extends TestCase
{
    private function arrayTransport(): ArrayTransport
    {
        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();
        if (! $transport instanceof ArrayTransport) {
            $this->fail('MAIL_MAILER=array expected in tests');
        }

        return $transport;
    }

    /** @return array<string, mixed> */
    private function sampleData(): array
    {
        return \App\Support\MailSampleData::attributes();
    }

    public function test_every_template_renders_and_sends_through_the_configured_mailer(): void
    {
        $mailer = $this->app->make(TransactionalMailer::class);
        $this->assertInstanceOf(BladeTransactionalMailer::class, $mailer);
        $transport = $this->arrayTransport();

        foreach (MailTemplateCatalog::KEYS as $key) {
            foreach ($this->sampleData() as $k => $v) {
                $mailer->attribute($k, $v);
            }
            $mailer->to('dest@example.com')->send($key);

            $sent = $transport->messages()->last();
            $this->assertNotNull($sent, $key);
            $html = (string) $sent->getOriginalMessage()->getHtmlBody();
            $subject = (string) $sent->getOriginalMessage()->getSubject();
            $this->assertNotSame('', trim($subject), $key);
            $this->assertStringNotContainsString('mail.', $subject, "$key: untranslated subject");
            $this->assertStringContainsString(config('brand.name'), $html, $key);
            $this->assertDoesNotMatchRegularExpression(LegacyContent::pattern(), $html.$subject, $key);
            if (str_starts_with($key, 'order_')) {
                $this->assertStringContainsString('141,52', $html, $key);
            }
        }
        $this->assertCount(count(MailTemplateCatalog::KEYS), $transport->messages());
    }

    public function test_subject_uses_scalar_attributes(): void
    {
        $mailer = $this->app->make(TransactionalMailer::class);
        $mailer->attribute('order_id', 7)->to('dest@example.com')->send('order_sent');

        $sent = $this->arrayTransport()->messages()->last();
        $this->assertStringContainsString('7', (string) $sent->getOriginalMessage()->getSubject());
    }

    public function test_the_send_test_command_uses_every_template(): void
    {
        $this->artisan('mail:send-test', ['to' => 'dest@example.com'])->assertSuccessful();
        $this->assertCount(count(MailTemplateCatalog::KEYS), $this->arrayTransport()->messages());
    }

    public function test_mandrill_brand_merge_vars_follow_the_installation(): void
    {
        config(['brand.name' => 'Negozio', 'brand.logo' => '/skins/x/logo.webp', 'brand.logo_mail' => '/skins/x/logo.png', 'brand.contact.email' => 'info@example.com']);
        $vars = \App\Drivers\Mail\MandrillTransactionalMailer::brandVars((array) config('brand'));
        $this->assertSame('Negozio', $vars['brand_name']);
        $this->assertStringEndsWith('/skins/x/logo.png', $vars['brand_logo_url'], 'the raster mail logo, absolute');
        $this->assertSame('info@example.com', $vars['brand_email']);
        $this->artisan('mail:mandrill-push', ['dir' => storage_path('framework/testing/none'), '--dry-run' => true])->assertFailed();
    }

    public function test_the_same_address_given_twice_receives_one_message(): void
    {
        $mailer = $this->app->make(TransactionalMailer::class);
        foreach (MailSampleData::attributes() as $k => $v) {
            $mailer->attribute($k, $v);
        }
        $mailer->to('admin@example.com')->to('admin@example.com')->send('contact_admin');
        $sent = $this->arrayTransport()->messages()->last();
        $this->assertCount(1, $sent->getOriginalMessage()->getTo());
    }
}
