<?php

declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Contracts\TransactionalMailer;
use App\Support\MailTemplateCatalog;
use InvalidArgumentException;
use MailchimpTransactional\ApiClient;

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
        $this->recipients[] = $email;

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

        $this->client->messages->sendTemplate([
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
            ],
        ]);

        $this->reset();
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
        foreach ($this->attributes as $name => $content) {
            $vars[] = [
                'name' => $name,
                'content' => $content,
            ];
        }

        return $vars;
    }
}
