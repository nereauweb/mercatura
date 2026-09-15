<?php

declare(strict_types=1);

namespace App\Drivers\Newsletter;

use App\Contracts\NewsletterProvider;
use App\Exceptions\AlreadySubscribedException;
use InvalidArgumentException;
use Juanparati\BrevoSuite\Client as BrevoClient;
use Throwable;

/**
 * Brevo contacts API. List id and attribute names come from
 * config('services.brevo'): attribute names are the installation's Brevo
 * account custom attributes.
 */
final class BrevoNewsletterProvider implements NewsletterProvider
{
    public function __construct(private readonly BrevoClient $client) {}

    public function key(): string
    {
        return 'brevo';
    }

    public function subscribe(array $contact): void
    {
        $email = $this->email($contact);
        $model = $this->client->getModel('Brevo\Client\Model\CreateContact', [
            'email' => $email,
            'attributes' => $this->attributes($contact),
            'listIds' => [$this->listId()],
        ]);

        try {
            $this->client->getApi('ContactsApi')->createContact($model);
        } catch (Throwable $e) {
            if ($this->isDuplicate($e)) {
                throw new AlreadySubscribedException($e->getMessage(), 0, $e);
            }

            throw $e;
        }
    }

    public function unsubscribe(string $email): void
    {
        $model = $this->client->getModel('Brevo\Client\Model\RemoveContactFromList', [
            'emails' => [$email],
        ]);

        try {
            $this->client->getApi('ContactsApi')->removeContactFromList($model, $this->listId());
        } catch (Throwable $e) {
            if (! $this->isNotFound($e)) {
                throw $e;
            }
        }
    }

    public function syncContact(array $contact): void
    {
        $model = $this->client->getModel('Brevo\Client\Model\CreateContact', [
            'email' => $this->email($contact),
            'attributes' => $this->attributes($contact),
            'listIds' => [$this->listId()],
            'updateEnabled' => true,
        ]);

        $this->client->getApi('ContactsApi')->createContact($model);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function email(array $contact): string
    {
        $email = $contact['email'] ?? null;
        if (! is_string($email) || $email === '') {
            throw new InvalidArgumentException('Newsletter subscribe requires an email.');
        }

        return $email;
    }

    private function listId(): int
    {
        return (int) config('services.brevo.newsletter_list_id');
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function attributes(array $contact): array
    {
        $map = (array) config('services.brevo.attributes', []);
        $attributes = [];
        foreach (['name', 'surname', 'phone', 'customer_type', 'company', 'activity'] as $key) {
            $attribute = $map[$key] ?? null;
            $value = $contact[$key] ?? null;
            if (is_string($attribute) && $attribute !== '' && $value !== null && $value !== '') {
                $attributes[$attribute] = $value;
            }
        }

        return $attributes;
    }

    private function isDuplicate(Throwable $e): bool
    {
        $haystack = strtolower($e->getMessage());
        if (method_exists($e, 'getResponseBody')) {
            $haystack .= ' '.strtolower((string) $e->getResponseBody());
        }

        return str_contains($haystack, 'duplicate_parameter')
            || str_contains($haystack, 'already exist')
            || str_contains($haystack, 'duplicate');
    }

    private function isNotFound(Throwable $e): bool
    {
        return (int) $e->getCode() === 404 || str_contains(strtolower($e->getMessage()), 'not found');
    }
}
