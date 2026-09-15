<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * docs/ARCHITECTURE.md §9 Phase 5 stop criterion: no SDK call outside app/Drivers.
 */
class DriverIsolationTest extends TestCase
{
    /** @var list<string> Namespaces and facades that only drivers may reference. */
    private const FORBIDDEN = [
        'Juanparati\\',
        'Brevo\\Client',
        'MailchimpMarketing\\',
        'MailchimpTransactional\\',
        'TimeHunter\\',
        'GoogleReCaptchaV3',
        'Stripe\\',
        'Laravel\\Cashier\\Cashier',
        'Srmklive\\',
        '\\PayPal::',
        'Algolia\\',
        'Product::search(',
    ];

    public function test_no_sdk_reference_outside_the_drivers_directory(): void
    {
        $finder = (new Finder)->files()
            ->in([base_path('app'), base_path('resources/views'), base_path('resources/js'), base_path('routes')])
            ->exclude(['Drivers'])
            ->name(['*.php', '*.js']);

        $violations = [];
        foreach ($finder as $file) {
            $contents = $file->getContents();
            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($contents, $needle)) {
                    $violations[] = $file->getRelativePathname().' → '.$needle;
                }
            }
        }

        $this->assertSame([], $violations, "SDK references outside app/Drivers:\n".implode("\n", $violations));
    }

    public function test_drivers_implement_a_contract(): void
    {
        $finder = (new Finder)->files()->in(app_path('Drivers'))->name('*.php');
        foreach ($finder as $file) {
            $class = 'App\\Drivers\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $this->assertTrue(class_exists($class), $class);
            $interfaces = array_filter(class_implements($class) ?: [], fn (string $i) => str_starts_with($i, 'App\\Contracts\\'));
            if (str_ends_with($class, 'Listener')) {
                continue;
            }
            $this->assertNotEmpty($interfaces, "$class implements no App\\Contracts interface");
        }
    }
}
