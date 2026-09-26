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
        /*
        | Hex codes for the colour labels the suppliers send without one
        | (catalog:color-codes, run after every products import). Lower-case
        | label => hex without #. A composite label ("blu/bianco", "nero-rosso")
        | is resolved part by part and stored as "HEX1/HEX2", the two-colour
        | swatch the storefront renders. Installations extend or override
        | the list in their own config.
        */
        'color_codes' => [
            'bianco' => 'FFFFFF', 'nero' => '000000', 'rosso' => 'E30613', 'blu' => '1F3A93', 'azzurro' => '5DADE2',
            'verde' => '2E8B57', 'giallo' => 'FFD700', 'arancio' => 'FF7F00', 'arancione' => 'FF7F00', 'grigio' => '9E9E9E',
            'marrone' => '7B4A2D', 'rosa' => 'F4A6C8', 'viola' => '7B3F9E', 'fucsia' => 'E5007E', 'bordeaux' => '7B1E2D',
            'oro' => 'D4AF37', 'argento' => 'C0C0C0', 'bronzo' => 'CD7F32', 'naturale' => 'E8DCC0', 'beige' => 'D9C7A5',
            'celeste' => '9BD3EE', 'lilla' => 'C8A2C8', 'prugna' => '6E2C4E', 'ruggine' => 'B7410E', 'indaco' => '4B0082',
            'jeans' => '4F6D9A', 'royal' => '2F4F8F', 'blu royal' => '2F4F8F', 'bluette' => '3B5BA5', 'navy' => '204060',
            'blue navy' => '204060', 'blu navy' => '204060', 'trasparente' => 'F2F2F2', 'mimetico' => '6B8E23/4B5320',
            'tricolore it' => '009246/CE2B37', 'tricolore fr' => '0055A4/EF4135', 'urban grey' => '808A8D', 'steel blue' => '4682B4',
            'verde acqua' => '66CDAA', 'verde acqua chiaro' => 'AFEEEE', 'verde matcha' => '9BB07F', 'verde mela' => '8DB600',
            'verde menta' => '98FF98', 'verde petrolio' => '006D6F', 'verde prato' => '4CAF50', 'verde scuro' => '1B5E20',
            'verde smeraldo' => '50C878', 'verde chiaro' => '90EE90', 'giallo chiaro' => 'FFF59D', 'grigio chiaro' => 'D3D3D3',
            'grigio scuro' => '555555', 'grigio ghiaccio' => 'DDE3E8', 'grigio verde' => '8F9E8B', 'blu grigio' => '6C7A89',
            'blu petrolio' => '1F4E5F', 'blu notte' => '0B1F3A', 'blu scuro' => '0B1F3A', 'blu chiaro' => '7FA8D8',
            'azzurro chiaro' => 'A9D6F5', 'rosa chiaro' => 'F8C8DC', 'rosso mattone' => '9B3B2E', 'rosso scuro' => '8B0000',
            'mela' => '8DB600', 'multicolore' => 'FF0000/0000FF',
        ],
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
    | Customizations (docs/03_CUSTOMIZATIONS.md §7). missing_days: a customization
    | of a live pipeline that the source stopped listing for this many days is
    | deactivated by cleanup:customizations (run after every customizations
    | import) and reactivated when it reappears; cleanup_days: after this many
    | days without being listed it is deleted (0 = never).
    */
    'customizations' => [
        'missing_days' => (int) env('MERCATURA_CUSTOMIZATIONS_MISSING_DAYS', 3),
        'cleanup_days' => (int) env('MERCATURA_CUSTOMIZATIONS_CLEANUP_DAYS', 90),
    ],

    /*
    | Storefront flows (docs/04_STOREFRONT_FLOWS.md). An installation picks
    | the configurator, checkout and quick-quote flow and switches the
    | optional behaviours on; the defaults are the original flows.
    */
    'storefront' => [
        'configurator' => env('MERCATURA_CONFIGURATOR', 'panel'),   // panel | modal
        'checkout' => env('MERCATURA_CHECKOUT', 'steps'),           // steps | onepage
        'quick_quote' => env('MERCATURA_QUICK_QUOTE', 'page'),      // page | modal
        'samples' => (bool) env('MERCATURA_SAMPLES', false),
        'shipping_date' => (bool) env('MERCATURA_SHIPPING_DATE', false),
        'artwork_in_configurator' => (bool) env('MERCATURA_ARTWORK_IN_CONFIGURATOR', false),
        'card_hover_image' => (bool) env('MERCATURA_CARD_HOVER_IMAGE', false),
    ],

    /*
    | Shipping date (App\Support\ShippingDate): working days from the order
    | day (from the next one after cutoff_hour), Saturdays, Sundays and the
    | listed holidays ('MM-DD' or 'YYYY-MM-DD') excluded.
    */
    /*
    | Staging switch: X-Robots-Tag noindex on every response, robots.txt that
    | disallows everything, noindex meta on every page and, when user and
    | password are set, HTTP basic auth in front of the whole site except the
    | listed paths (payment webhooks, health check).
    */
    // Extra robots.txt lines of the installation (ARCHITECTURE §12: additions by configuration).
    'robots_extra' => array_values(array_filter(array_map('trim', explode('|', (string) env('MERCATURA_ROBOTS_EXTRA', ''))))),

    'staging' => [
        'enabled' => (bool) env('MERCATURA_STAGING', false),
        'user' => env('MERCATURA_STAGING_USER'),
        'password' => env('MERCATURA_STAGING_PASSWORD'),
        'except' => ['up', 'stripe/webhook', 'ordine/*/pagamento/*', 'skins/*', 'build/*', 'storage/*'],
    ],

    'delivery' => [
        'cutoff_hour' => (int) env('MERCATURA_DELIVERY_CUTOFF_HOUR', 12),
        'holidays' => array_values(array_filter(array_map('trim', explode(',', (string) env('MERCATURA_DELIVERY_HOLIDAYS', '01-01,01-06,04-25,05-01,06-02,08-15,11-01,12-08,12-25,12-26'))))),
    ],

    'features' => [
        // Supplier connectors (docs/ARCHITECTURE.md §13): each installed package
        // carries its own switch (`connector-<key>.enabled`, env
        // MERCATURA_CONNECTOR_<KEY>=true). An entry here overrides it.
        'connectors' => [],
    ],

];
