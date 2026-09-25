<?php

declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Contracts\TransactionalMailer;
use App\Support\MailTemplateCatalog;
use InvalidArgumentException;
use MailchimpTransactional\ApiClient;
use RuntimeException;

final class MandrillTransactionalMailer implements TransactionalMailer
{
    /** @var list<string> */
    private array $recipients = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        private MailTemplateCatalog $catalog,
        private ApiClient $client,
    ) {}

    /** Client from config('services.mandrill'). */
    public static function fromConfig(MailTemplateCatalog $catalog): self
    {
        $client = new ApiClient;
        $client->setApiKey((string) config('services.mandrill.key'));

        return new self($catalog, $client);
    }

    public function to(string $email): self
    {
        // The technical and the merchant address are often the same mailbox: one message, not two.
        if (! in_array($email, $this->recipients, true)) {
            $this->recipients[] = $email;
        }

        return $this;
    }

    public function attribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function send(string $templateKey): void
    {
        if ($this->recipients === []) {
            throw new InvalidArgumentException("Cannot send template [{$templateKey}] without recipients.");
        }

        $fromEmail = config('services.mandrill.from_email');
        if (! is_string($fromEmail) || $fromEmail === '') {
            throw new InvalidArgumentException('Missing Mandrill from email (MANDRILL_FROM_EMAIL / MAIL_FROM_ADDRESS).');
        }

        $result = $this->client->messages->sendTemplate([
            'template_name' => $this->catalog->mandrillSlug($templateKey),
            'template_content' => [],
            'message' => [
                'from_email' => $fromEmail,
                'from_name' => config('services.mandrill.from_name'),
                'to' => array_map(fn (string $email) => [
                    'email' => $email,
                    'type' => 'to',
                ], $this->recipients),
                'merge_language' => 'handlebars',
                'global_merge_vars' => $this->mergeVars(),
                // Per recipient: the address the mail goes to, for the "sent to" footer line.
                'merge_vars' => array_map(fn (string $email) => ['rcpt' => $email, 'vars' => [['name' => 'recipient_email', 'content' => $email]]], $this->recipients),
            ],
        ]);
        $this->reset();

        // The API answers per recipient; an invalid key or a rejected address comes back as a status, not an exception.
        if (! is_array($result)) {
            $error = is_object($result) && isset($result->message) ? (string) $result->message : 'unexpected response';
            throw new RuntimeException("Mandrill refused template [{$templateKey}]: {$error}");
        }
        foreach ($result as $row) {
            $status = is_object($row) ? ($row->status ?? '') : (is_array($row) ? ($row['status'] ?? '') : '');
            if (in_array($status, ['rejected', 'invalid'], true)) {
                $reason = is_object($row) ? ($row->reject_reason ?? $status) : ($row['reject_reason'] ?? $status);
                throw new RuntimeException("Mandrill did not deliver template [{$templateKey}]: {$reason}");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $brand
     * @return array<string, string>
     */
    public static function brandVars(array $brand): array
    {
        $logo = (string) ($brand['logo_mail'] ?? $brand['logo'] ?? '');

        return [
            'brand_name' => (string) ($brand['name'] ?? ''),
            'brand_url' => url('/'),
            'brand_logo_url' => $logo === '' ? '' : (str_starts_with($logo, 'http') ? $logo : url($logo)),
            'brand_email' => (string) ($brand['contact']['email'] ?? ''),
            'brand_phone' => (string) ($brand['contact']['phone'] ?? ''),
        ];
    }

    /**
     * Slugs of the templates in the account (template management, mail:mandrill-push).
     *
     * @return list<string>
     */
    public function templateSlugs(): array
    {
        $list = $this->client->templates->list();
        if (! is_array($list)) {
            throw new RuntimeException('Mandrill refused the request: '.(is_object($list) && isset($list->message) ? (string) $list->message : 'unexpected response'));
        }

        return array_values(array_filter(array_map(fn ($t) => (string) ($t->slug ?? ''), $list)));
    }

    /**
     * Creates or updates one template; returns its slug.
     *
     * @param  array{name: string, code: string, subject: string, from_email: string, from_name: string, labels: list<string>, publish: bool}  $payload
     */
    public function upsertTemplate(array $payload, bool $exists): string
    {
        $result = $exists ? $this->client->templates->update($payload) : $this->client->templates->add($payload);
        if (! is_object($result) || ! isset($result->slug)) {
            throw new RuntimeException($payload['name'].': '.(is_object($result) && isset($result->message) ? (string) $result->message : (string) json_encode($result)));
        }

        return (string) $result->slug;
    }

    public function reset(): self
    {
        $this->recipients = [];
        $this->attributes = [];

        return $this;
    }

    /**
     * @return list<array{name: string, content: mixed}>
     */
    private function mergeVars(): array
    {
        $vars = [];
        // The installation's identity, so one template set serves any skin (logo, name, site, contacts).
        $brand = (array) config('brand');
        foreach (self::brandVars($brand) as $name => $content) {
            $vars[] = ['name' => $name, 'content' => $content];
        }
        foreach ($this->attributes as $name => $content) {
            $vars[] = [
                'name' => $name,
                'content' => $content,
            ];
        }

        return $vars;
    }
}
