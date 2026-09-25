<?php

declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Contracts\TransactionalMailer;
use App\Support\MailTemplateCatalog;
use Juanparati\BrevoSuite\Facades\Template;

/** Brevo hosted templates (ids from config/mail-templates.php, BREVO_TPL_*). */
final class BrevoTransactionalMailer implements TransactionalMailer
{
    public function __construct(private readonly MailTemplateCatalog $catalog) {}

    /** @var list<string> */
    private array $recipients = [];

    public function to(string $email): self
    {
        // The technical and the merchant address are often the same mailbox: one message, not two.
        if (in_array($email, $this->recipients, true)) {
            return $this;
        }
        $this->recipients[] = $email;
        Template::to($email);

        return $this;
    }

    public function attribute(string $key, mixed $value): self
    {
        Template::attribute($key, $value);

        return $this;
    }

    public function send(string $templateKey): void
    {
        Template::send($this->catalog->brevoId($templateKey));
        $this->reset();
    }

    public function reset(): self
    {
        $this->recipients = [];
        Template::reset();

        return $this;
    }
}
