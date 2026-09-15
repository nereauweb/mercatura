<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Tag Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for Google Tag Manager, Google Ads, and
    | Google Analytics tracking IDs.
    |
    */

    'container_id' => env('GTM_CONTAINER_ID'),

    'google_ads_id' => env('GOOGLE_ADS_ID'),

    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Consent Mode v2
    |--------------------------------------------------------------------------
    | When true: GTM loads always, consent default "denied", update to "granted"
    | on banner accept. When false: GTM loads only when cookies_analytics=1 (legacy).
    | Set GTM_CONSENTMODE_V2=true in .env to enable.
    */
    'consentmode_v2' => env('GTM_CONSENTMODE_V2', false),

    /*
    | Google Ads conversion labels, sent only after analytics consent and
    | only when set. Format: the label part after "<ads id>/".
    */
    'conversions' => [
        'add_to_cart' => env('GOOGLE_ADS_CONVERSION_ADD_TO_CART'),
        'purchase' => env('GOOGLE_ADS_CONVERSION_PURCHASE'),
        'quotation_sent' => env('GOOGLE_ADS_CONVERSION_QUOTATION'),
    ],

];
