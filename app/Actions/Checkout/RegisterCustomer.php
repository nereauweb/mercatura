<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Registration during checkout: user with the customer role, billing and
 * shipping addresses, customer profile, login, welcome mail, optional
 * newsletter subscription. Input is the validated data of
 * CustomerFormRules::registerAuthRules.
 */
final class RegisterCustomer
{
    public function __construct(
        private readonly TransactionalMailer $mailer,
        private readonly NewsletterProvider $newsletter,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function handle(array $data): User
    {
        $get = static fn (string $key): mixed => $data[$key] ?? null;
        $user = User::create([
            'name' => $get('name').' '.$get('surname'),
            'email' => $get('email'),
            'password' => Hash::make((string) $get('password')),
        ]);
        $user->assignRole('customer');
        $billing = CustomerAddress::create([
            'address' => $get('bill_address'),
            'province' => $get('bill_province'),
            'city' => $get('bill_city'),
            'zip_code' => $get('bill_zip_code'),
            'country' => $get('bill_country'),
            'notes' => $get('bill_notes'),
        ]);
        $shipping = CustomerAddress::create([
            'address' => $get('shipping_address'),
            'province' => $get('shipping_province'),
            'city' => $get('shipping_city'),
            'zip_code' => $get('shipping_zip_code'),
            'country' => $get('shipping_country'),
            'notes' => $get('shipping_notes'),
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_type' => $get('customer_type'),
            'email' => $get('email'),
            'phone' => $get('phone'),
            'name' => $get('name'),
            'surname' => $get('surname'),
            'company' => $get('company'),
            'activity' => $get('activity'),
            'tax_code' => $get('tax_code'),
            'vat_code' => Customer::normalizedVatForStore($get('customer_type'), $get('vat_code')),
            'sdi_code' => $get('sdi_code'),
            'ipa_code' => Customer::normalizedIpaForStore($get('customer_type'), $get('ipa_code')),
            'cig_code' => Customer::normalizedCigForStore($get('customer_type'), $get('cig_code')),
            'pec' => $get('pec'),
            'shipping_address_id' => $shipping->id,
            'billing_address_id' => $billing->id,
        ]);
        FrontendDebugLog::registrazioneCliente('checkout_register:customer_created', ['user_id' => $user->id, 'customer_id' => $customer->id]);
        Auth::login($user);

        $this->mailer->reset();
        $this->mailer->attribute('name', $user->name);
        $this->mailer->to($user->email);
        $this->mailer->send('welcome');
        $this->mailer->reset();

        $optIn = filter_var($get('subscribe_newsletter'), FILTER_VALIDATE_BOOLEAN);
        FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:start', ['source' => 'checkout_register', 'user_id' => $user->id, 'opt_in' => $optIn]);
        if ($optIn) {
            $context = ['source' => 'checkout_register', 'user_id' => $user->id, 'provider' => config('mercatura.providers.newsletter')];
            try {
                $this->newsletter->subscribe([
                    'email' => $get('email'),
                    'name' => $get('name'),
                    'surname' => $get('surname'),
                    'phone' => $get('phone'),
                    'customer_type' => $get('customer_type'),
                    'company' => $get('company'),
                    'activity' => $get('activity'),
                ]);
                FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:provider_ok', $context);
            } catch (\Exception $e) {
                CaughtExceptionLogger::error('RegisterCustomer newsletter subscribe failed', $e, $context);
                FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:provider_error', $context + ['error' => $e->getMessage()]);
            }
        }

        return $user;
    }
}
