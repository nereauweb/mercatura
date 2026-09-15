<?php

namespace App\Support;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CustomerFormRules
{
    private const STRICT_EMAIL_REGEX = '/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/';

    public static function fiscalAndBillingRules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'customer_type' => ['required', 'string', Rule::in(Customer::CUSTOMER_TYPES)],
            'company' => [
                Rule::requiredIf(fn () => Customer::requiresVat($request->input('customer_type'))),
                'nullable',
                'string',
                'max:255',
            ],
            'activity' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[+]?[0-9][0-9\s\-()]{7,22}$/'],
            'tax_code' => ['nullable', 'string', 'regex:/^(?:[A-Za-z0-9]{16}|[0-9]{11})$/'],
            'vat_code' => [
                Rule::requiredIf(fn () => Customer::requiresVat($request->input('customer_type'))),
                Rule::prohibitedIf(fn () => $request->input('customer_type') === Customer::TYPE_PRIVATE),
                'nullable',
                'string',
                'size:11',
                'regex:/^[0-9]{11}$/',
            ],
            'sdi_code' => ['nullable', 'string', 'regex:/^(?:[A-Za-z0-9]{7}|[A-Za-z0-9]{6}|OO[0-9]{11})$/'],
            'pec' => ['nullable', 'email', 'regex:'.self::STRICT_EMAIL_REGEX, 'max:255'],
            'ipa_code' => [
                Rule::prohibitedIf(fn () => ! Customer::isPublicAdmin($request->input('customer_type'))),
                Rule::requiredIf(fn () => Customer::isPublicAdmin($request->input('customer_type'))),
                'nullable',
                'string',
                'size:6',
                'regex:/^[A-Za-z0-9]{6}$/',
            ],
            'cig_code' => [
                Rule::prohibitedIf(fn () => ! Customer::isPublicAdmin($request->input('customer_type'))),
                Rule::requiredIf(fn () => Customer::isPublicAdmin($request->input('customer_type'))),
                'nullable',
                'string',
                'size:10',
                'regex:/^[A-Za-z0-9]{10}$/',
            ],
            'bill_address' => ['required', 'string', 'max:255', 'not_regex:/@/'],
            'bill_city' => ['required', 'string', 'max:100'],
            'bill_province' => ['required', 'string', Rule::in(Customer::PROVINCES)],
            'bill_zip_code' => ['required', 'string', 'max:20'],
            'bill_country' => ['required', 'string', 'max:100'],
            'bill_notes' => ['nullable', 'string', 'max:500'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['nullable', 'string', 'max:100'],
            'shipping_province' => ['nullable', 'string', Rule::in(array_merge([''], Customer::PROVINCES))],
            'shipping_zip_code' => ['nullable', 'string', 'max:20'],
            'shipping_country' => ['nullable', 'string', 'max:100'],
            'shipping_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function profileEmailRules(int $userId): array
    {
        return [
            'email' => ['required', 'email', 'regex:'.self::STRICT_EMAIL_REGEX, 'max:255', Rule::unique('users', 'email')->ignore($userId)],
        ];
    }

    /**
     * Campi cliente annidati (richiesta preventivo) — stessi vincoli anagrafici/fiscali
     * delle altre form, senza CF/P.IVA/indirizzo (non presenti nel form preventivo).
     */
    public static function quotationCustomerRules(Request $request): array
    {
        // Nel form preventivo, per richiesta del cliente, gli UNICI campi
        // obbligatori sono l'email e il consenso alla privacy. Gli altri
        // campi anagrafici sono facoltativi; quando valorizzati devono
        // comunque rispettare i formati previsti.
        return [
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.surname' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.customer_type' => ['nullable', 'string', Rule::in(Customer::CUSTOMER_TYPES)],
            // Ragione sociale obbligatoria solo se il visitatore dichiara una forma giuridica diversa da Privato.
            'customer.company' => [
                Rule::requiredIf(fn () => filled($request->input('customer.customer_type')) && Customer::requiresVat($request->input('customer.customer_type'))),
                'nullable',
                'string',
                'max:255',
            ],
            'customer.activity' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50', 'regex:/^[+]?[0-9][0-9\s\-()]{7,22}$/'],
        ];
    }

    public static function registerAuthRules(Request $request): array
    {
        return array_merge(
            self::fiscalAndBillingRules($request),
            [
                'email' => ['required', 'email', 'regex:'.self::STRICT_EMAIL_REGEX, 'max:255', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
                'consent_gdpr' => ['required', 'accepted'],
                'consent_terms' => ['required', 'accepted'],
                'subscribe_newsletter' => ['nullable', 'boolean'],
            ],
        );
    }
}
