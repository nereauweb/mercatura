<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Support\Connectors\BaseConnector;
use App\Support\Customizations\LinePricer;
use App\Support\ImportConnectors;
use App\Support\ShippingDate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CustomizationFixture;
use Tests\TestCase;

/** docs/04_STOREFRONT_FLOWS.md §4.5: working days, cutoff, holidays, restock, connector override. */
final class ShippingDateTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_working_days_skip_weekends_and_holidays_and_respect_the_cutoff(): void
    {
        config(['mercatura.delivery.holidays' => ['12-25', '2026-09-22'], 'mercatura.delivery.cutoff_hour' => 12]);
        // Friday 18 September 2026, morning: start today; 3 working days → Thursday 24 (Tuesday 22 is a holiday).
        $this->assertSame('2026-09-24', ShippingDate::addWorkingDays(ShippingDate::start(Carbon::parse('2026-09-18 09:00')), 3)->toDateString());
        // The same Friday after the cutoff: start Monday 21 → Friday 25.
        $this->assertSame('2026-09-25', ShippingDate::addWorkingDays(ShippingDate::start(Carbon::parse('2026-09-18 15:00')), 3)->toDateString());
        // Starting on a Saturday counts from Monday.
        $this->assertSame('2026-09-21', ShippingDate::addWorkingDays(Carbon::parse('2026-09-19'), 0)->toDateString());
        $this->assertFalse(ShippingDate::isWorkingDay(Carbon::parse('2026-12-25')));
    }

    public function test_a_line_ships_after_product_and_customization_days_and_after_a_restock_when_needed(): void
    {
        $this->seed(CoreSeeder::class);
        $f = CustomizationFixture::create();
        config(['mercatura.delivery.holidays' => [], 'mercatura.delivery.cutoff_hour' => 23]);
        Carbon::setTestNow('2026-09-14 09:00'); // a Monday

        // product 7 (no connector) + default print 5, plus the chosen print's 5 = 17 working days → 7 October.
        $line = app(LinePricer::class)->price([[$f->a->id, 100]], [$f->screenOneColorA->id], false);
        $this->assertSame('2026-10-07', ShippingDate::for($line)->toDateString());

        // Beyond the stock with a restock date: counted from the restock.
        $f->a->update(['stock' => 10, 'next_stock_date' => '2026-10-01', 'next_stock_quantity' => 500]);
        $line = app(LinePricer::class)->price([[$f->a->id, 100]], [$f->screenOneColorA->id], false);
        $this->assertSame('2026-10-26', ShippingDate::for($line)->toDateString(), '1 October + 17 working days');

        // A connector may answer for its products.
        $this->app->make(ImportConnectors::class)->register(new class extends BaseConnector
        {
            public function key(): string
            {
                return 'own';
            }

            public function label(): string
            {
                return 'Own';
            }

            public function shippingDate(Product $product, int $workingDays): CarbonInterface
            {
                return Carbon::parse('2026-12-01');
            }
        });
        $this->assertSame('2026-12-01', ShippingDate::for($line)->toDateString());
    }
}
