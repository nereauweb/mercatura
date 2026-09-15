<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | Provider credentials. The active drivers are chosen in
    | config/mercatura.php (providers.mail, providers.newsletter).
    */
    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'newsletter_list_id' => (int) env('BREVO_NEWSLETTER_LIST_ID', 0),
        // Contact attribute names of the installation's Brevo account.
        'attributes' => [
            'name' => env('BREVO_ATTR_NAME', 'FIRSTNAME'),
            'surname' => env('BREVO_ATTR_SURNAME', 'LASTNAME'),
            'phone' => env('BREVO_ATTR_PHONE', 'SMS'),
            'customer_type' => env('BREVO_ATTR_TYPE', 'TYPE'),
            'company' => env('BREVO_ATTR_COMPANY', 'COMPANY'),
            'activity' => env('BREVO_ATTR_ACTIVITY', 'ACTIVITY'),
        ],
    ],

    'mandrill' => [
        'key' => env('MANDRILL_API_KEY'),
        'from_email' => env('MANDRILL_FROM_EMAIL', env('MAIL_FROM_ADDRESS')),
        'from_name' => env('MANDRILL_FROM_NAME', env('MAIL_FROM_NAME')),
    ],

    'mailchimp' => [
        'key' => env('MAILCHIMP_API_KEY'),
        'server' => env('MAILCHIMP_SERVER_PREFIX'),
        'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
        'merge_fields' => [
            'name' => env('MAILCHIMP_MERGE_NAME', 'FNAME'),
            'surname' => env('MAILCHIMP_MERGE_SURNAME', 'LNAME'),
            'phone' => env('MAILCHIMP_MERGE_PHONE', 'PHONE'),
            'customer_type' => env('MAILCHIMP_MERGE_TYPE', 'TIPO'),
            'company' => env('MAILCHIMP_MERGE_COMPANY', 'COMPANY'),
            'activity' => env('MAILCHIMP_MERGE_ACTIVITY', 'ACTIVITY'),
        ],
    ],

    'tickets' => [
        'url' => env('TICKET_API_URL'),
        'token' => env('TICKET_API_TOKEN'),
        'customer_id' => env('TICKET_CUSTOMER_ID'),
    ],

    'zendesk' => [
        'widget_key' => env('ZENDESK_WIDGET_KEY'),
    ],

];
