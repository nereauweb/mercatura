<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Customizations\PricedLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The date a configured line ships (docs/04_STOREFRONT_FLOWS.md §4.5):
 * working days from the order day (the next one after the cutoff hour),
 * skipping weekends and the configured holidays; an article ordered
 * beyond its stock counts from its restock date; a connector may answer
 * from the supplier instead (ImportConnector::shippingDate).
 */
final class ShippingDate
{
    public static function for(PricedLine $line, ?CarbonInterface $from = null): CarbonInterface
    {
        $product = $line->product;
        $days = (int) $product->processing_days() + $line->processingDays;
        $start = self::start($from);
        foreach ($line->articles as $article) {
            $variant = $article->variant;
            if ($article->quantity > (int) $variant->stock && $variant->next_stock_date) {
                $restock = Carbon::parse((string) $variant->next_stock_date)->startOfDay();
                if ($restock->greaterThan($start)) {
                    $start = $restock;
                }
            }
        }
        $fromConnector = app(ImportConnectors::class)->forSource($product->source)?->shippingDate($product, $days);

        return $fromConnector ?? self::addWorkingDays($start, $days);
    }

    /** Today, or tomorrow once the cutoff hour has passed. */
    public static function start(?CarbonInterface $from = null): Carbon
    {
        $now = Carbon::instance($from ?? now());
        $start = $now->copy()->startOfDay();
        if ($now->hour >= (int) config('mercatura.delivery.cutoff_hour', 12)) {
            $start->addDay();
        }

        return $start;
    }

    public static function addWorkingDays(CarbonInterface $from, int $days): Carbon
    {
        $date = Carbon::instance($from)->startOfDay();
        while (! self::isWorkingDay($date)) {
            $date->addDay();
        }
        for ($i = 0; $i < $days; $i++) {
            $date->addDay();
            while (! self::isWorkingDay($date)) {
                $date->addDay();
            }
        }

        return $date;
    }

    public static function isWorkingDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }
        foreach ((array) config('mercatura.delivery.holidays', []) as $holiday) {
            $holiday = trim((string) $holiday);
            if ($holiday === $date->format('m-d') || $holiday === $date->format('Y-m-d')) {
                return false;
            }
        }

        return true;
    }
}
