<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\MailTemplateCatalog;
use InvalidArgumentException;
use Tests\TestCase;

class MailTemplateCatalogTest extends TestCase
{
    public function test_every_logical_key_has_a_blade_view_and_is_listed(): void
    {
        $catalog = $this->app->make(MailTemplateCatalog::class);

        $this->assertSame(MailTemplateCatalog::KEYS, $catalog->keys());
        foreach (MailTemplateCatalog::KEYS as $key) {
            $this->assertSame('mail.'.$key, $catalog->view($key), $key);
        }
    }

    public function test_brevo_ids_come_from_config_without_installation_defaults(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Brevo template id for [welcome]');
        (new MailTemplateCatalog(['welcome' => ['brevo' => null, 'view' => 'mail.welcome']]))->brevoId('welcome');
    }

    public function test_brevo_id_is_cast_to_int(): void
    {
        $this->assertSame(7, (new MailTemplateCatalog(['welcome' => ['brevo' => '7']]))->brevoId('welcome'));
    }

    public function test_unknown_key_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(MailTemplateCatalog::class)->brevoId('does_not_exist');
    }

    public function test_empty_mandrill_slug_throws(): void
    {
        $catalog = new MailTemplateCatalog(['welcome' => ['brevo' => 7, 'mandrill' => null, 'view' => 'mail.welcome']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing Mandrill template slug for [welcome].');
        $catalog->mandrillSlug('welcome');
    }
}
