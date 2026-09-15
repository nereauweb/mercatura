<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transactional mail templates
    |--------------------------------------------------------------------------
    |
    | Logical keys used by TransactionalMailer. Each entry maps to a hosted
    | template id/slug per provider (brevo, mandrill) and to the in-app Blade
    | view rendered by every other mail provider (postmark, smtp, log…).
    | Hosted ids belong to the installation: set them in .env.
    |
    */

    'contact_admin' => [
        'brevo' => env('BREVO_TPL_CONTACT_ADMIN'),
        'mandrill' => env('MANDRILL_TPL_CONTACT_ADMIN'),
        'view' => 'mail.contact_admin',
    ],

    'welcome' => [
        'brevo' => env('BREVO_TPL_WELCOME'),
        'mandrill' => env('MANDRILL_TPL_WELCOME'),
        'view' => 'mail.welcome',
    ],

    'password_reset' => [
        'brevo' => env('BREVO_TPL_PASSWORD_RESET'),
        'mandrill' => env('MANDRILL_TPL_PASSWORD_RESET'),
        'view' => 'mail.password_reset',
    ],

    'quotation_customer' => [
        'brevo' => env('BREVO_TPL_QUOTATION_CUSTOMER'),
        'mandrill' => env('MANDRILL_TPL_QUOTATION_CUSTOMER'),
        'view' => 'mail.quotation_customer',
    ],

    'quotation_admin' => [
        'brevo' => env('BREVO_TPL_QUOTATION_ADMIN'),
        'mandrill' => env('MANDRILL_TPL_QUOTATION_ADMIN'),
        'view' => 'mail.quotation_admin',
    ],

    'register_admin' => [
        'brevo' => env('BREVO_TPL_REGISTER_ADMIN'),
        'mandrill' => env('MANDRILL_TPL_REGISTER_ADMIN'),
        'view' => 'mail.register_admin',
    ],

    'order_stored_user' => [
        'brevo' => env('BREVO_TPL_ORDER_STORED_USER'),
        'mandrill' => env('MANDRILL_TPL_ORDER_STORED_USER'),
        'view' => 'mail.order_stored_user',
    ],

    'order_stored_admin' => [
        'brevo' => env('BREVO_TPL_ORDER_STORED_ADMIN'),
        'mandrill' => env('MANDRILL_TPL_ORDER_STORED_ADMIN'),
        'view' => 'mail.order_stored_admin',
    ],

    'order_paid_user' => [
        'brevo' => env('BREVO_TPL_ORDER_PAID_USER'),
        'mandrill' => env('MANDRILL_TPL_ORDER_PAID_USER'),
        'view' => 'mail.order_paid_user',
    ],

    'order_paid_admin' => [
        'brevo' => env('BREVO_TPL_ORDER_PAID_ADMIN'),
        'mandrill' => env('MANDRILL_TPL_ORDER_PAID_ADMIN'),
        'view' => 'mail.order_paid_admin',
    ],

    'order_updated' => [
        'brevo' => env('BREVO_TPL_ORDER_UPDATED'),
        'mandrill' => env('MANDRILL_TPL_ORDER_UPDATED'),
        'view' => 'mail.order_updated',
    ],

    'order_sent' => [
        'brevo' => env('BREVO_TPL_ORDER_SENT'),
        'mandrill' => env('MANDRILL_TPL_ORDER_SENT'),
        'view' => 'mail.order_sent',
    ],

    'order_cancelled' => [
        'brevo' => env('BREVO_TPL_ORDER_CANCELLED'),
        'mandrill' => env('MANDRILL_TPL_ORDER_CANCELLED'),
        'view' => 'mail.order_cancelled',
    ],

];
