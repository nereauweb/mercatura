<?php

declare(strict_types=1);

namespace App\Support\Customizations;

/** The pricing constants of config/mercatura.php `pricing`, in one place. */
final class Pricing
{
    public static function vatRate(): float
    {
        return (float) config('mercatura.pricing.vat_rate', 0.22);
    }

    /** VAT on an amount, rounded to the cent. */
    public static function vat(float $amount): float
    {
        return round($amount * self::vatRate(), 2);
    }

    /** Delivery cost for an order of goods worth `itemsPrice` (excl. VAT). */
    public static function deliveryCost(float $itemsPrice): float
    {
        return $itemsPrice > (float) config('mercatura.pricing.free_delivery_from', 500) ? 0.0 : (float) config('mercatura.pricing.delivery_cost', 16);
    }

    public static function underMinimumSurcharge(): float
    {
        return (float) config('mercatura.pricing.under_minimum_surcharge', 40);
    }
}
