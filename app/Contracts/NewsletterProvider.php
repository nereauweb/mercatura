<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\AlreadySubscribedException;

/**
 * Marketing list membership. Selected by config('mercatura.providers.newsletter').
 * Contact keys: email (required), name, surname, phone, customer_type,
 * company, activity; drivers ignore keys they cannot map.
 */
interface NewsletterProvider
{
    /** Driver name, e.g. "brevo", "mailchimp" or "null". */
    public function key(): string;

    /**
     * Add a contact to the default list.
     *
     * @param  array<string, mixed>  $contact
     *
     * @throws AlreadySubscribedException when the contact is already on the list
     */
    public function subscribe(array $contact): void;

    /** Remove a contact from the default list; no error when absent. */
    public function unsubscribe(string $email): void;

    /**
     * Create or update a contact without raising on duplicates.
     *
     * @param  array<string, mixed>  $contact
     */
    public function syncContact(array $contact): void;
}
