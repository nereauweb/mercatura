<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Configurazione delle email utilizzate nell'applicazione per le notifiche
    | amministrative e tecniche.
    |
    */

    'merchant' => env('EMAIL_MERCHANT', 'merchant@example.com'),
    'technical' => env('EMAIL_TECHNICAL', 'technical@example.com'),
];
