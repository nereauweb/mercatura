<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Models\ImportData\VariantPrintingColor;
use App\Models\ProductVariant;
use App\Support\Connectors\PrintingPipeline;

/**
 * Prices a configured line (docs/03_CUSTOMIZATIONS.md §4.3): the one
 * algorithm behind the configurator summary, the cart, the order and the
 * demo seeder.
 *
 * - The article tier and the markup band are chosen by the quantity of the
 *   whole line; every article's price is cost + markup.
 * - Each chosen option is re-resolved on every article (the equivalent
 *   technique + position + size + option of that variant) and priced on the
 *   line quantity's tier with the article's markup; packaging, when asked,
 *   at the stored packaging price.
 * - Start cost and setup × multiplier (0 counts as 1) are counted once per
 *   option; a line below the highest minimum quantity of its options pays
 *   the flat surcharge.
 */
final class LinePricer
{
    /**
     * @param  list<array{0: int, 1: int}>  $articles  [variant id, quantity]
     * @param  list<int>  $optionIds  chosen VariantPrintingColor ids
     */
    public function price(array $articles, array $optionIds, bool $packaging): PricedLine
    {
        $quantity = 0;
        foreach ($articles as [$variantId, $articleQuantity]) {
            $quantity += (int) $articleQuantity;
        }

        $options = [];
        foreach ($optionIds as $optionId) {
            $option = VariantPrintingColor::query()->find((int) $optionId);
            if ($option instanceof VariantPrintingColor && PrintingPipeline::colorIsLive($option)) {
                $options[] = $option;
            }
        }

        $pricedArticles = [];
        $additionalCosts = 0.0;
        $price = 0.0;
        foreach ($articles as [$variantId, $articleQuantity]) {
            $variant = ProductVariant::query()->findOrFail((int) $variantId);
            $articleQuantity = (int) $articleQuantity;
            $original = (float) $variant->price_per_quantity($quantity, true);
            $markupPercent = (float) $variant->get_markup_percent($quantity, $original);
            $unitPrice = $original + round($original * ($markupPercent / 100), 2);
            $articlePrice = $articleQuantity * $unitPrice;
            $articleAdditional = $articleQuantity * (float) $variant->additional_unit_costs_per_quantity($articleQuantity);

            $customizations = [];
            foreach ($options as $chosen) {
                $option = $chosen->sibling($variant->id);
                $costs = $option->calculate_print_price($quantity, $articleQuantity, $packaging, false, $markupPercent);
                $customizations[] = new PricedArticleCustomization(
                    chosen: $chosen,
                    option: $option,
                    label: $option->printing_label(),
                    quantity: $articleQuantity,
                    unitPrice: (float) $costs['unit_price'],
                    price: (float) $costs['price'],
                    packagingUnitPrice: $packaging ? (float) $costs['packaging_unit_price'] : null,
                    packagingPrice: $packaging ? (float) $costs['packaging_price'] : 0.0,
                );
                $price += (float) $costs['price'] + ($packaging ? (float) $costs['packaging_price'] : 0.0);
            }

            $pricedArticles[] = new PricedArticle($variant, $articleQuantity, $original, $markupPercent, $unitPrice, $articlePrice, $articleAdditional, $customizations);
            $price += $articlePrice;
            $additionalCosts += $articleAdditional;
        }

        $pricedCustomizations = [];
        $minimum = 0;
        $processingDays = 0;
        foreach ($options as $option) {
            $printing = $option->printing_size->printing;
            $startCost = (float) $option->start_cost > 0 ? (float) $option->start_cost : 0.0;
            $setupMultiplier = (int) $option->setup_multiplier ?: 1;
            $setupPrice = (float) $option->setup * $setupMultiplier;
            $pricedCustomizations[] = new PricedCustomization(
                option: $option,
                printing: $printing,
                label: $option->printing_label(),
                startCost: $startCost,
                setup: (float) $option->setup,
                setupMultiplier: $setupMultiplier,
                setupPrice: $setupPrice,
                minimumQuantity: (int) $printing->minimum_quantity,
                processingDays: (int) $printing->processing_days,
            );
            $price += $startCost + $setupPrice;
            $minimum = max($minimum, (int) $printing->minimum_quantity);
            $processingDays = max($processingDays, (int) $printing->processing_days);
        }

        $surcharge = $quantity < $minimum ? Pricing::underMinimumSurcharge() : 0.0;
        $price += $surcharge;

        return new PricedLine(
            product: $pricedArticles[0]->variant->product,
            quantity: $quantity,
            packaging: $packaging,
            articles: $pricedArticles,
            customizations: $pricedCustomizations,
            minimumQuantity: $minimum,
            surcharge: $surcharge,
            processingDays: $processingDays,
            additionalCosts: $additionalCosts,
            price: $price,
        );
    }
}
