<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\NewsletterProvider;
use App\Exceptions\AlreadySubscribedException;
use Tests\TestCase;

/** "Utente già iscritto" only when the provider says so; any other failure says to retry. */
final class NewsletterErrorsTest extends TestCase
{
    private function providerThrowing(\Throwable $error): void
    {
        $this->app->instance(NewsletterProvider::class, new class($error) implements NewsletterProvider
        {
            public function __construct(private \Throwable $error) {}

            public function key(): string
            {
                return 'fake';
            }

            public function subscribe(array $contact): void
            {
                throw $this->error;
            }

            public function unsubscribe(string $email): void {}

            public function syncContact(array $contact): void {}
        });
    }

    /** @return array<string, string> */
    private function form(): array
    {
        return ['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'mario@example.com', 'phone' => '+39 333 1234567', 'consent_gdpr' => '1'];
    }

    public function test_an_address_already_subscribed_is_told_so(): void
    {
        $this->providerThrowing(new AlreadySubscribedException('Contact already subscribed'));
        $this->from('/newsletter')->post(route('frontend.newsletter.register'), $this->form())
            ->assertSessionHasErrors(['Brevo' => __('frontend.newsletter.already_subscribed')]);
    }

    public function test_any_other_failure_asks_to_retry(): void
    {
        $this->providerThrowing(new \RuntimeException('API down'));
        $this->from('/newsletter')->post(route('frontend.newsletter.register'), $this->form())
            ->assertSessionHasErrors(['Brevo' => __('frontend.newsletter.subscribe_failed')]);
    }
}
