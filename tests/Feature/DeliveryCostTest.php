<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Customizations\Pricing;
use Tests\TestCase;

final class DeliveryCostTest extends TestCase
{
    public function test_delivery_is_free_from_the_threshold_included(): void
    {
        config(['mercatura.pricing.free_delivery_from' => 500, 'mercatura.pricing.delivery_cost' => 16]);

        $this->assertSame(16.0, Pricing::deliveryCost(499.99));
        $this->assertSame(0.0, Pricing::deliveryCost(500.0), '"da 500 €" includes 500 €');
        $this->assertSame(0.0, Pricing::deliveryCost(800.0));
    }
}
