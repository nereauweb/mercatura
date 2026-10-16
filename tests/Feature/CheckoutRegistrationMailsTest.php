<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Checkout\RegisterCustomer;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Mail\Transport\ArrayTransport;
use Tests\TestCase;

final class CheckoutRegistrationMailsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_registration_in_the_checkout_notifies_the_admin_like_registrati(): void
    {
        $this->seed(CoreSeeder::class);
        config(['emails.merchant' => 'negozio@example.com']);

        app(RegisterCustomer::class)->handle([
            'name' => 'Lucia', 'surname' => 'Bianchi', 'email' => 'lucia.checkout@example.com', 'password' => 'Password123!',
            'customer_type' => 'Azienda', 'company' => 'Bianchi srl', 'phone' => '+39 333 7654321', 'consent_gdpr' => '1', 'consent_terms' => '1',
            'billing_address' => 'Via Roma 1', 'billing_city' => 'Milano', 'billing_province' => 'MI', 'billing_zip_code' => '20100', 'billing_country' => 'IT',
        ]);

        $transport = $this->app->make('mail.manager')->mailer('array')->getSymfonyTransport();
        $recipients = $transport instanceof ArrayTransport
            ? $transport->messages()->flatMap(fn ($m) => array_map(fn ($a) => $a->getAddress(), $m->getOriginalMessage()->getTo()))->all()
            : [];
        $this->assertContains('lucia.checkout@example.com', $recipients, 'welcome');
        $this->assertContains('negozio@example.com', $recipients, 'register_admin');
    }
}
