<?php

declare(strict_types=1);

namespace App\Support;

/** Attributes covering every transactional template, for tests and for mail:send-test. */
final class MailSampleData
{
    /** @return array<string, mixed> */
    public static function attributes(): array
    {
        return [
            'name' => 'Mario', 'surname' => 'Rossi', 'company' => 'ACME', 'email' => 'mario@example.com',
            'phone' => '0123456', 'activity' => 'Commercio', 'customer_type' => 'Azienda',
            'subject' => 'Info', 'message' => "Riga 1\nRiga 2", 'consent_gdpr' => 'Sì', 'subscribe_newsletter' => 'No',
            'gdpr' => 'Sì', 'terms' => 'Sì', 'newsletter' => 'No', 'reset_link' => 'https://example.com/reset/abc',
            'contacts_link' => 'https://example.com/contattaci', 'gdpr_link' => 'https://example.com/contenuti/privacy-policy',
            'quotation_date' => '01/02/2026 10:00',
            'customer_name' => 'Mario', 'customer_surname' => 'Rossi', 'customer_company' => 'ACME',
            'customer_email' => 'mario@example.com', 'customer_phone' => '0123456',
            'customer_activity' => 'Commercio', 'customer_vat_code' => 'IT00000000000',
            'customer_tax_code' => '', 'customer_pec' => '', 'customer_sdi_code' => '', 'customer_ipa_code' => '', 'customer_cig_code' => '',
            'quotation_items' => [['sku' => 'SKU1', 'name' => 'Penna', 'quantity' => 100, 'printing' => 'Sì', 'color' => 'Blu', 'size' => '', 'notes' => 'Logo', 'image' => 'https://example.com/q.jpg']],
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
}
