<?php

declare(strict_types=1);

namespace App\Drivers\Newsletter;

use App\Contracts\NewsletterProvider;
use Illuminate\Support\Facades\Log;

/** No marketing list: every call is logged and succeeds. Default for tests and local installs. */
final class NullNewsletterProvider implements NewsletterProvider
{
    public function key(): string
    {
        return 'null';
    }

    public function subscribe(array $contact): void
    {
        Log::info('Newsletter subscribe (null driver)', ['email' => $contact['email'] ?? null]);
    }

    public function unsubscribe(string $email): void
    {
        Log::info('Newsletter unsubscribe (null driver)', ['email' => $email]);
    }

    public function syncContact(array $contact): void
    {
        Log::info('Newsletter sync (null driver)', ['email' => $contact['email'] ?? null]);
    }
}
