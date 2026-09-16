<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Models\Customer;

/**
 * Updates the customer's fiscal data and both addresses from the validated
 * data of CustomerFormRules::fiscalAndBillingRules (+ profileEmailRules).
 * Pass only the address groups present in `$data`: a missing group is left untouched.
 */
final class SaveCustomerProfile
{
    /** @param  array<string, mixed>  $data */
    public function handle(Customer $customer, array $data): void
    {
        $get = static fn (string $key): mixed => $data[$key] ?? null;
        if (array_key_exists('name', $data)) {
            $customer->update([
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
            ]);
        }
        if (array_key_exists('bill_address', $data)) {
            $customer->billing_address->update([
                'address' => $get('bill_address'),
                'province' => $get('bill_province'),
                'city' => $get('bill_city'),
                'zip_code' => $get('bill_zip_code'),
                'country' => $get('bill_country'),
                'notes' => $get('bill_notes'),
            ]);
        }
        if (array_key_exists('shipping_address', $data)) {
            $customer->shipping_address->update([
                'address' => $get('shipping_address'),
                'province' => $get('shipping_province'),
                'city' => $get('shipping_city'),
                'zip_code' => $get('shipping_zip_code'),
                'country' => $get('shipping_country'),
                'notes' => $get('shipping_notes'),
            ]);
        }
    }
}
