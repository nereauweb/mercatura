<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Brand identity — neutral defaults
|--------------------------------------------------------------------------
|
| Everything that identifies an installation: name, logos, contacts, legal
| and bank details, social links. The values here are placeholders and
| must stay neutral. An installation overrides them from
| resources/skins/<name>/brand.php (merged over these defaults at boot);
| secrets never go here, they belong to .env.
|
*/

return [

    'name' => env('BRAND_NAME', 'Mercatura'),
    'legal_name' => 'Mercatura',
    'tagline' => 'Il tuo negozio online',

    'logo' => '/img/logo.png',

    // Mail clients do not all render WebP/SVG: a raster copy for the mail layout (null = same as logo).

    'logo_mail' => env('BRAND_LOGO_MAIL'),

    // CMS page slugs shown in the header's utility bar (left of Contatti) instead of the category bar.

    'utility_pages' => array_values(array_filter(array_map('trim', explode(',', (string) env('BRAND_UTILITY_PAGES', ''))))),
    'logo_width' => 500,   // intrinsic pixel size of the logo file, for width/height attributes
    'logo_height' => 103,
    'logo_mark' => '/img/logo-mark.png',
    'og_image' => '/img/og-image.png',
    'favicon_path' => '/favicon',
    'theme_color' => '#ffffff',

    'contact' => [
        'email' => 'info@example.com',
        'phone' => '',
        'whatsapp' => '',
        'address' => [
            'street' => '',
            'zip' => '',
            'city' => '',
            'province' => '',
            'country' => 'IT',
        ],
        'opening_hours' => '',
        // Embed URL of a map (e.g. Google Maps embed) shown on the contact page; null hides the map.
        'map_embed_url' => null,
    ],

    'meta' => [
        'default_title' => null, // null: "<name> | <tagline>"
    ],

    'legal' => [
        'vat' => '',
        'tax_code' => '',
        'rea' => '',
        'share_capital' => '',
        'pec' => '',
        'sdi' => '',
    ],

    'bank' => [
        'name' => '',
        'iban' => '',
        'bic' => '',
    ],

    /*
    | Certifications or quality marks shown on the storefront (home, green
    | section). Each entry: label, image path, intrinsic width and height.
    */
    'certifications' => [],

    'social' => [
        'facebook' => null,
        'instagram' => null,
        'linkedin' => null,
        'youtube' => null,
    ],

];
