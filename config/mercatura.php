<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mercatura core configuration
|--------------------------------------------------------------------------
|
| Installation-level switches: which skin is active, which providers are
| selected and which optional features are on. Identity lives in
| config/brand.php. See docs/ARCHITECTURE.md §3–§5.
|
*/

return [

    /*
    | Skin directory under resources/skins/ whose files shadow the core
    | storefront views, lang files and brand defaults. Sanitised to
    | [a-z0-9_-] at boot; null or an unknown name means the core default.
    */
    'skin' => env('MERCATURA_SKIN'),

    /*
    | Provider selection (docs/01_STACK_SPECIFICATION.md §4). Each value names
    | the driver bound to the corresponding contract by DriverServiceProvider;
    | drivers live under app/Drivers and nothing else calls a provider SDK.
    | - search: any Scout engine (algolia, meilisearch, typesense, database,
    |   collection, null); mirrored into scout.driver.
    | - captcha: recaptcha | null
    | - mail: brevo | mandrill (hosted templates) or the name of a mailer in
    |   config/mail.php (postmark, smtp, log, array…) rendering the mail.*
    |   Blade views; "default" means config('mail.default').
    | - newsletter: brevo | mailchimp | null
    | - ai: null (anthropic reserved)
    | - personalization: null
    */
    'providers' => [
        'search' => env('MERCATURA_SEARCH_PROVIDER', env('SCOUT_DRIVER', 'collection')),
        'captcha' => env('MERCATURA_CAPTCHA_PROVIDER', 'recaptcha'),
        'mail' => env('MAIL_PROVIDER', 'default'),
        'newsletter' => env('NEWSLETTER_PROVIDER', 'null'),
        'ai' => env('MERCATURA_AI_PROVIDER', 'null'),
        'personalization' => env('MERCATURA_PERSONALIZATION_PROVIDER', 'null'),
    ],

    /*
    | Hosted payment gateways: checkout payment method → PaymentGateway driver.
    | Methods listed in checkout.payment_methods without a gateway here
    | (bank_transfer) complete the order without redirecting.
    */
    'payments' => [
        'gateways' => [
            'stripe' => App\Drivers\Payment\StripePaymentGateway::class,
            'paypal' => App\Drivers\Payment\PayPalPaymentGateway::class,
        ],
    ],

    /*
    | Feature flags: plain booleans (docs/ARCHITECTURE.md §11). Supplier
    | connectors are installation features and default to off: the import
    | jobs skip a disabled connector and its commands refuse to run
    | (App\Support\ImportConnectors).
    */
    /*
    | Catalogue presentation switches.
    | - attributes: ids in product_attributes used by the product page tables;
    |   they follow the installation's import mapping.
    | - per_size_price_table_categories: category ids whose products show a
    |   price table with one row per size (e.g. items priced by capacity).
    */
    'catalog' => [
        'attributes' => [
            'brand' => (int) env('MERCATURA_ATTR_BRAND', 3),
            'material' => (int) env('MERCATURA_ATTR_MATERIAL', 4),
            'pack_pieces' => (int) env('MERCATURA_ATTR_PACK_PIECES', 11),
            'pack_length' => (int) env('MERCATURA_ATTR_PACK_LENGTH', 12),
            'pack_width' => (int) env('MERCATURA_ATTR_PACK_WIDTH', 13),
            'pack_height' => (int) env('MERCATURA_ATTR_PACK_HEIGHT', 14),
            'pack_weight' => (int) env('MERCATURA_ATTR_PACK_WEIGHT', 15),
        ],
        'per_size_price_table_categories' => array_filter(array_map('intval', explode(',', (string) env('MERCATURA_PER_SIZE_PRICE_TABLE_CATEGORIES', '')))),
        // product_sizes row meaning "one size", assigned to variants saved without a size.
        'one_size_id' => (int) env('MERCATURA_ONE_SIZE_ID', 52),
    ],

    /*
    | Slugs of the CMS pages linked from consent checkboxes and the footer.
    */
    'legal_pages' => [
        'privacy' => env('MERCATURA_PAGE_PRIVACY', 'privacy-policy'),
        'terms' => env('MERCATURA_PAGE_TERMS', 'condizioni-di-vendita'),
    ],

    /*
    | Checkout defaults that are not price logic: delivery cost applies
    | below the free-shipping threshold (values mirror the cart code).
    */
    'checkout' => [
        'payment_methods' => array_filter(array_map('trim', explode(',', (string) env('MERCATURA_PAYMENT_METHODS', 'bank_transfer,stripe,paypal')))),
    ],

    /*
    | Line and cart pricing constants (App\Support\Customizations\LinePricer,
    | App\Support\Customizations\Pricing). One VAT rate for everything sold;
    | delivery is free from `free_delivery_from` of goods (excl. VAT); a line
    | whose quantity is below the minimum of a chosen customization pays the
    | flat surcharge.
    */
    'pricing' => [
        'vat_rate' => (float) env('MERCATURA_VAT_RATE', 0.22),
        'delivery_cost' => (float) env('MERCATURA_DELIVERY_COST', 16),
        'free_delivery_from' => (float) env('MERCATURA_FREE_DELIVERY_FROM', 500),
        'under_minimum_surcharge' => (float) env('MERCATURA_UNDER_MINIMUM_SURCHARGE', 40),
    ],

    /*
    | Customizations (docs/03_CUSTOMIZATIONS.md). cleanup_days: a customization
    | of a live pipeline that no import has touched for this many days is
    | deleted by cleanup:customizations (run after every customizations import).
    */
    'customizations' => [
        'cleanup_days' => (int) env('MERCATURA_CUSTOMIZATIONS_CLEANUP_DAYS', 90),
    ],

    'features' => [
        // Supplier connectors (docs/ARCHITECTURE.md §13): each installed package
        // carries its own switch (`connector-<key>.enabled`, env
        // MERCATURA_CONNECTOR_<KEY>=true). An entry here overrides it.
        'connectors' => [],
    ],

];
