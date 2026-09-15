<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ImportConnector;
use App\Models\ImportData\NormalizedRulesPricingProducts;
use App\Models\ImportData\VariantPrinting;
use App\Models\Product;
use App\Support\Connectors\BaseConnector;
use App\Support\Connectors\MarkupRules;
use App\Support\Connectors\PrintingPipeline;
use App\Support\ImportConnectors;
use Tests\TestCase;

/** The connector framework of docs/ARCHITECTURE.md §13, exercised with a fake supplier. */
class ConnectorFrameworkTest extends TestCase
{
    private function fake(bool $enabled = true): ImportConnector
    {
        return new class($enabled) extends BaseConnector
        {
            public function __construct(private bool $on) {}

            public function key(): string
            {
                return 'acme';
            }

            public function label(): string
            {
                return 'ACME Gadgets';
            }

            public function sourceAliases(): array
            {
                return ['Acme', 'ACM'];
            }

            public function enabled(): bool
            {
                return $this->on;
            }

            public function commands(string $stage): array
            {
                return $stage === self::STAGE_DOWNLOAD ? ['acme:download'] : [];
            }

            public function processingDays(Product $product): int
            {
                return 12;
            }

            public function markupPercent(float $percent, ?NormalizedRulesPricingProducts $rule, float $condition, int $condition3, ?string $sku): float
            {
                return max($percent, 42.0);
            }

            public function printingPipelines(): array
            {
                return ['v2'];
            }
        };
    }

    public function test_registry_matches_every_spelling_of_a_source(): void
    {
        $registry = new ImportConnectors;
        $registry->register($this->fake());

        $this->assertSame('acme', $registry->forSource('ACM')?->key());
        $this->assertSame('acme', $registry->forSource('acme gadgets')?->key());
        $this->assertNull($registry->forSource('other'));
        $this->assertSame(['acme:download'], $registry->forProcessSource('Acme')[0]->commands(ImportConnector::STAGE_DOWNLOAD));
        $this->assertSame([], $registry->forProcessSource('other'));
        $this->assertEqualsCanonicalizing(['acme', 'ACME Gadgets', 'Acme', 'ACM'], $registry->allSourceValues());
    }

    public function test_model_hooks_and_markup_go_through_the_connector(): void
    {
        $registry = $this->app->make(ImportConnectors::class);
        $registry->register($this->fake());

        $product = new Product(['source' => 'Acme', 'sku' => 'X']);
        $this->assertSame(12, $product->processing_days('none'));
        $this->assertSame(BaseConnector::DEFAULT_PROCESSING_DAYS, (new Product(['source' => 'own']))->processing_days('none'));
        $this->assertSame(42.0, $this->app->make(MarkupRules::class)->percent(1.0, 0, 'ACM', 'X'));
        $this->assertSame(0.0, $this->app->make(MarkupRules::class)->percent(1_000_000_000.0, 0, 'own', 'X'), 'no band, no connector: 0');
    }

    public function test_printing_pipeline_filter_only_touches_declared_sources(): void
    {
        $registry = $this->app->make(ImportConnectors::class);
        $registry->register($this->fake());

        $sql = PrintingPipeline::apply(VariantPrinting::query())->toSql();
        $this->assertStringContainsString('`source` not in', $sql);
        $this->assertStringContainsString('`pipeline` in', $sql);
        $this->assertTrue(PrintingPipeline::printingIsLive(new VariantPrinting(['source' => 'own', 'pipeline' => 'anything'])));
        $this->assertTrue(PrintingPipeline::printingIsLive(new VariantPrinting(['source' => 'acme', 'pipeline' => 'v2'])));
        $this->assertFalse(PrintingPipeline::printingIsLive(new VariantPrinting(['source' => 'Acme', 'pipeline' => 'v1'])));
    }

    public function test_disabled_connectors_are_registered_but_not_enabled(): void
    {
        $registry = new ImportConnectors;
        $registry->register($this->fake(false));
        $this->assertCount(1, $registry->all());
        $this->assertSame([], $registry->enabled());
        $this->assertSame([], $registry->forProcessSource('all'));
    }
}
