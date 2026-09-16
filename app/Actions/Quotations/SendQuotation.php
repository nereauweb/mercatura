<?php

declare(strict_types=1);

namespace App\Actions\Quotations;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Quotation;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;

/**
 * Registers a quotation request and sends the two notifications (customer,
 * merchant), subscribing the customer to the newsletter when asked. Shared
 * by the quotation page and the quick-quote modal (docs/04_STOREFRONT_FLOWS.md §4.4)
 * so both produce the same rows and mails.
 */
final class SendQuotation
{
    private const CUSTOMER_FIELDS = ['name', 'surname', 'email', 'company', 'customer_type', 'activity', 'phone'];

    public function __construct(
        private readonly TransactionalMailer $mailer,
        private readonly NewsletterProvider $newsletter,
    ) {}

    /**
     * @param  array<string, mixed>  $customer  name, surname, email, company, customer_type, activity, phone
     * @param  list<array<string, mixed>>  $products  session rows: sku, name, quantity, printing, image, color, size, notes
     */
    public function handle(array $customer, array $products, bool $gdpr, bool $newsletter): Quotation
    {
        $customer = array_merge(
            array_fill_keys(self::CUSTOMER_FIELDS, ''),
            array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $customer),
        );

        $quotation = Quotation::create([
            'customer_email' => $customer['email'],
            'customer_type' => $customer['customer_type'],
            'customer_company' => $customer['company'],
            'customer_name' => $customer['name'],
            'customer_surname' => $customer['surname'],
            'customer_phone' => $customer['phone'],
            'customer_activity' => $customer['activity'],
        ]);
        FrontendDebugLog::preventivo('store:quotation_created', ['quotation_id' => $quotation->id]);

        $items = [];
        foreach ($products as $row) {
            $row['quantity'] = (int) ($row['quantity'] ?? 0);
            $quotation->items()->create([
                'sku' => $row['sku'] ?? '',
                'name' => $row['name'] ?? '',
                'quantity' => $row['quantity'],
                'customization' => $row['printing'] ?? __('frontend.quotation.no'),
                'image' => $row['image'] ?? '',
                'color' => $row['color'] ?? '',
                'size' => $row['size'] ?? '',
                'notes' => $row['notes'] ?? '',
            ]);
            $items[] = $row;
        }

        $attributes = [
            'quotation_date' => $quotation->created_at->format('d/m/Y H:i'),
            'customer_type' => $customer['customer_type'],
            'customer_company' => $customer['company'],
            'customer_activity' => $customer['activity'],
            'customer_name' => $customer['name'],
            'customer_surname' => $customer['surname'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'quotation_items' => $items,
            'gdpr' => $gdpr ? 'Sì' : 'No',
            'newsletter' => $newsletter ? 'Sì' : 'No',
            'contacts_link' => route('frontend.contacts.index'),
            'gdpr_link' => url('/').'/contenuti/privacy-policy',
        ];
        $this->send('quotation_customer', [$customer['email']], $attributes);
        $this->send('quotation_admin', [config('emails.technical'), config('emails.merchant')], $attributes);

        if ($newsletter) {
            $this->subscribe($quotation, $customer);
        }

        return $quotation;
    }

    /**
     * @param  list<mixed>  $recipients
     * @param  array<string, mixed>  $attributes
     */
    private function send(string $template, array $recipients, array $attributes): void
    {
        $this->mailer->reset();
        foreach ($attributes as $key => $value) {
            $this->mailer->attribute($key, $value);
        }
        foreach ($recipients as $recipient) {
            if (is_string($recipient) && $recipient !== '') {
                $this->mailer->to($recipient);
            }
        }
        $this->mailer->send($template);
        $this->mailer->reset();
    }

    /** @param  array<string, string>  $customer */
    private function subscribe(Quotation $quotation, array $customer): void
    {
        $context = ['source' => 'quotation', 'quotation_id' => $quotation->id, 'provider' => config('mercatura.providers.newsletter')];
        FrontendDebugLog::newsletter('quotation:subscribe_newsletter:start', $context);
        try {
            $this->newsletter->subscribe([
                'email' => $customer['email'],
                'name' => $customer['name'],
                'surname' => $customer['surname'],
                'phone' => $customer['phone'],
                'customer_type' => $customer['customer_type'],
                'company' => $customer['company'],
                'activity' => $customer['activity'],
            ]);
            FrontendDebugLog::newsletter('quotation:subscribe_newsletter:provider_ok', $context);
        } catch (\Exception $e) {
            CaughtExceptionLogger::error('SendQuotation newsletter subscribe failed', $e, $context);
            FrontendDebugLog::newsletter('quotation:subscribe_newsletter:provider_error', $context + ['error' => $e->getMessage()]);
        }
    }
}
