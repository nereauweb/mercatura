<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\TransactionalMailer;
use App\Drivers\Mail\BladeTransactionalMailer;
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
        return [
            'name' => 'Mario', 'surname' => 'Rossi', 'company' => 'ACME', 'email' => 'mario@example.com',
            'phone' => '0123456', 'activity' => 'Commercio', 'customer_type' => 'Azienda',
            'subject' => 'Info', 'message' => "Riga 1\nRiga 2", 'consent_gdpr' => 'Sì', 'subscribe_newsletter' => 'No',
            'gdpr' => 'Sì', 'terms' => 'Sì', 'newsletter' => 'No', 'reset_link' => 'https://example.com/reset/abc',
            'quotation_date' => '01/02/2026 10:00',
            'customer_name' => 'Mario', 'customer_surname' => 'Rossi', 'customer_company' => 'ACME',
            'customer_email' => 'mario@example.com', 'customer_phone' => '0123456',
            'customer_activity' => 'Commercio', 'customer_vat_code' => 'IT00000000000',
            'quotation_items' => [['sku' => 'SKU1', 'name' => 'Penna', 'quantity' => 100, 'printing' => 'Sì', 'color' => 'Blu', 'size' => '', 'notes' => 'Logo']],
            'order_id' => 42, 'order_date' => '01/02/2026 10:00', 'order_status' => 'Registrato', 'payment_status' => 'In attesa',
            'order_tracking_code' => 'TRK1', 'order_items_price' => '100,00', 'order_delivery_cost' => '16,00',
            'order_total_price' => '116,00', 'order_total_tax' => '25,52', 'order_total_taxed_price' => '141,52',
            'order_address' => 'Via Roma 1', 'order_zip_code' => '00100', 'order_city' => 'Roma', 'order_province' => 'RM', 'order_country' => 'IT',
            'order_notes' => '', 'order_items' => [[
                'product_sku' => 'SKU1', 'product_name' => 'Penna', 'product_image_url' => 'https://example.com/p.jpg',
                'quantity' => 100, 'article_quantities' => '100 x SKU1-BLU', 'price' => '100,00', 'printings' => 'Personalizzazioni: Tampografia',
            ]],
        ];
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
}
