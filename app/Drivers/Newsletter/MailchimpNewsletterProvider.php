<?php

declare(strict_types=1);

namespace App\Drivers\Newsletter;

use App\Contracts\NewsletterProvider;
use App\Exceptions\AlreadySubscribedException;
use InvalidArgumentException;
use MailchimpMarketing\Api\ListsApi;
use MailchimpMarketing\ApiClient;
use Throwable;

/** Mailchimp Marketing audience. Audience id and merge tags come from config('services.mailchimp'). */
final class MailchimpNewsletterProvider implements NewsletterProvider
{
    public function __construct(private readonly ListsApi $lists) {}

    /** Client from config('services.mailchimp'). */
    public static function fromConfig(): self
    {
        $client = new ApiClient;
        $client->setConfig([
            'apiKey' => config('services.mailchimp.key'),
            'server' => config('services.mailchimp.server'),
        ]);

        return new self(new ListsApi($client));
    }

    public function key(): string
    {
        return 'mailchimp';
    }

    public function subscribe(array $contact): void
    {
        $email = $this->email($contact);
        $listId = $this->listId();
        $hash = $this->hash($email);

        $member = null;
        try {
            $member = $this->lists->getListMember($listId, $hash);
        } catch (Throwable $e) {
            if (! $this->isNotFound($e)) {
                throw $e;
            }
        }

        if ($member !== null) {
            $status = is_object($member) ? (string) ($member->status ?? '') : (string) (((array) $member)['status'] ?? '');
            if (in_array($status, ['subscribed', 'pending'], true)) {
                throw new AlreadySubscribedException('Contact already subscribed');
            }
            // Unsubscribed, cleaned or archived contact: Mailchimp only takes it back through its
            // own confirmation (compliance state), so it becomes pending and receives the opt-in mail.
            $this->lists->setListMember($listId, $hash, [
                'email_address' => $email,
                'status' => 'pending',
                'merge_fields' => $this->mergeFields($contact),
            ]);

            return;
        }

        try {
            $this->lists->addListMember($listId, [
                'email_address' => $email,
                'status' => 'subscribed',
                'merge_fields' => $this->mergeFields($contact),
            ]);
        } catch (Throwable $e) {
            if ($this->isDuplicate($e)) {
                throw new AlreadySubscribedException($e->getMessage(), 0, $e);
            }

            throw $e;
        }
    }

    public function unsubscribe(string $email): void
    {
        try {
            $this->lists->updateListMember($this->listId(), $this->hash($email), [
                'status' => 'unsubscribed',
            ]);
        } catch (Throwable $e) {
            if (! $this->isNotFound($e)) {
                throw $e;
            }
        }
    }

    public function syncContact(array $contact): void
    {
        $email = $this->email($contact);
        $this->lists->setListMember($this->listId(), $this->hash($email), [
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'merge_fields' => $this->mergeFields($contact),
        ]);
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

    private function listId(): string
    {
        $listId = config('services.mailchimp.audience_id');
        if (! is_string($listId) || $listId === '') {
            throw new InvalidArgumentException('Missing Mailchimp audience id (MAILCHIMP_AUDIENCE_ID).');
        }

        return $listId;
    }

    private function hash(string $email): string
    {
        return md5(strtolower($email));
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function mergeFields(array $contact): array
    {
        $map = (array) config('services.mailchimp.merge_fields', []);
        $fields = [];
        foreach (['name', 'surname', 'phone', 'customer_type', 'company', 'activity'] as $key) {
            $tag = $map[$key] ?? null;
            $value = $contact[$key] ?? null;
            if (is_string($tag) && $tag !== '' && $value !== null && $value !== '') {
                $fields[$tag] = $value;
            }
        }

        return $fields;
    }

    private function isNotFound(Throwable $e): bool
    {
        if ((int) $e->getCode() === 404) {
            return true;
        }
        $message = strtolower($e->getMessage());

        return str_contains($message, '404')
            || str_contains($message, 'resource not found')
            || str_contains($message, 'not found');
    }

    private function isDuplicate(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'member exists')
            || str_contains($message, 'already a list member')
            || str_contains($message, 'duplicate');
    }
}
