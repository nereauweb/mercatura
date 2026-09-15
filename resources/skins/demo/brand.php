<?php

declare(strict_types=1);

/*
| Demo skin identity. Merged over config/brand.php at boot; only the keys
| that differ are listed. Copy this file into a new skin as a starting point.
*/

return [
    'name' => 'Mercatura Demo',
    'legal_name' => 'Mercatura Demo S.r.l.',
    'tagline' => 'Il negozio dimostrativo del core Mercatura',
    'logo' => '/skins/demo/logo.svg',
    'logo_width' => 220,
    'logo_height' => 48,
    'contact' => [
        'email' => 'demo@example.com',
        'phone' => '+39 000 000 0000',
        'address' => [
            'street' => 'Via dell\'Esempio 1',
            'zip' => '00000',
            'city' => 'Città',
            'province' => 'XX',
        ],
    ],
];
