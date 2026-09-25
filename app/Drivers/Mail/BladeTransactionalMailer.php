<?php

declare(strict_types=1);

namespace App\Drivers\Mail;

use App\Contracts\TransactionalMailer;
use App\Support\MailTemplateCatalog;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Mail\Message;
use InvalidArgumentException;

/**
 * Renders the in-app Blade templates (mail.* views, copy in lang mail.php)
 * and sends them through a Laravel mailer: Postmark, Brevo SMTP/API, smtp,
 * log, array… Any mailer configured in config/mail.php works, so the same
 * templates serve production and the demo instance.
 */
final class BladeTransactionalMailer implements TransactionalMailer
{
    /** @var list<string> */
    private array $recipients = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(
        private readonly MailTemplateCatalog $catalog,
        private readonly MailFactory $mail,
        private readonly string $mailer,
    ) {}

    public function mailer(): string
    {
        return $this->mailer;
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
        $view = $this->catalog->view($templateKey);
        if ($view === null) {
            throw new InvalidArgumentException("Missing Blade view for mail template [{$templateKey}].");
        }

        $recipients = array_values(array_unique($this->recipients));
        $replacements = array_filter($this->attributes, fn ($v) => is_scalar($v)) + ['brand' => config('brand.name')];
        $subject = __('mail.'.$templateKey.'.subject', $replacements);

        $this->mail->mailer($this->mailer)->send(
            $view,
            ['data' => $this->attributes, 'templateKey' => $templateKey],
            function (Message $message) use ($recipients, $subject): void {
                $message->to($recipients)->subject($subject);
            },
        );

        $this->reset();
    }

    public function reset(): self
    {
        $this->recipients = [];
        $this->attributes = [];

        return $this;
    }
}
