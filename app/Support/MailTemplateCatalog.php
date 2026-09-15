<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Logical transactional mail templates (config/mail-templates.php): each key
 * maps to a hosted template id per provider and to the in-app Blade view.
 */
final class MailTemplateCatalog
{
    public const KEYS = [
        'contact_admin',
        'welcome',
        'password_reset',
        'quotation_customer',
        'quotation_admin',
        'register_admin',
        'order_stored_user',
        'order_stored_admin',
        'order_paid_user',
        'order_paid_admin',
        'order_updated',
        'order_sent',
        'order_cancelled',
    ];

    /**
     * @param  array<string, array<string, mixed>>  $templates
     */
    public function __construct(private readonly array $templates) {}

    public function brevoId(string $key): int
    {
        $this->assertKnown($key);

        $id = $this->templates[$key]['brevo'] ?? null;
        if ($id === null || $id === '') {
            throw new InvalidArgumentException("Missing Brevo template id for [{$key}] (BREVO_TPL_*).");
        }

        return (int) $id;
    }

    public function mandrillSlug(string $key): string
    {
        $this->assertKnown($key);

        $slug = $this->templates[$key]['mandrill'] ?? null;
        if (! is_string($slug) || $slug === '') {
            throw new InvalidArgumentException("Missing Mandrill template slug for [{$key}].");
        }

        return $slug;
    }

    public function view(string $key): ?string
    {
        $this->assertKnown($key);

        $view = $this->templates[$key]['view'] ?? null;

        return is_string($view) && $view !== '' ? $view : null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->templates);
    }

    private function assertKnown(string $key): void
    {
        if (! array_key_exists($key, $this->templates)) {
            throw new InvalidArgumentException("Unknown mail template [{$key}].");
        }
    }
}
