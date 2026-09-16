<?php

declare(strict_types=1);

namespace App\Support\Customizations;

use App\Support\ShippingDate;

/** The priced line as the modal configurator reads it (docs/04_STOREFRONT_FLOWS.md §4.1): amounts as floats, labels ready, totals with shipping and VAT. */
final class LineJson
{
    /** @return array<string, mixed> */
    public static function from(PricedLine $line): array
    {
        $shipping = Pricing::deliveryCost($line->price);
        $taxable = $line->price + $shipping;
        $vat = Pricing::vat($taxable);

        return [
            'quantity' => $line->quantity,
            'sample' => $line->sample,
            'packaging' => $line->packaging,
            'articles' => array_map(fn (PricedArticle $article): array => [
                'variant_id' => $article->variant->id,
                'sku' => (string) $article->variant->sku,
                'color' => (string) ($article->variant->color->label ?? ''),
                'color_code' => $article->variant->color ? (string) $article->variant->color->render_code() : '',
                'size' => $article->variant->size ? (string) $article->variant->size->shown_label() : '',
                'quantity' => $article->quantity,
                'unit_price' => round($article->unitPrice, 2),
                'price' => round($article->price, 2),
                'customizations' => array_map(fn (PricedArticleCustomization $c): array => [
                    'option_id' => $c->chosen->id,
                    'label' => $c->label,
                    'quantity' => $c->quantity,
                    'unit_price' => round($c->unitPrice, 2),
                    'price' => round($c->price, 2),
                    'packaging_unit_price' => $c->packagingUnitPrice === null ? null : round($c->packagingUnitPrice, 2),
                    'packaging_price' => round($c->packagingPrice, 2),
                ], $article->customizations),
            ], $line->articles),
            'customizations' => array_map(fn (PricedCustomization $c): array => [
                'option_id' => $c->option->id,
                'label' => $c->label,
                'technique' => (string) $c->printing->technique_label,
                'position' => (string) $c->printing->position_label,
                'start_cost' => round($c->startCost, 2),
                'setup' => round($c->setup, 2),
                'setup_multiplier' => $c->setupMultiplier,
                'setup_price' => round($c->setupPrice, 2),
                'minimum_quantity' => $c->minimumQuantity,
                'processing_days' => $c->processingDays,
            ], $line->customizations),
            'minimum_quantity' => $line->minimumQuantity,
            'surcharge' => round($line->surcharge, 2),
            'additional_costs' => round($line->additionalCosts, 2),
            'price' => round($line->price, 2),
            'shipping' => round($shipping, 2),
            'taxable' => round($taxable, 2),
            'vat_rate' => Pricing::vatRate(),
            'vat' => $vat,
            'total' => round($taxable + $vat + $line->additionalCosts, 2),
            'unit_price' => $line->unitPrice(),
            'processing_days' => $line->processingDays,
            'shipping_date' => config('mercatura.storefront.shipping_date') ? ShippingDate::for($line)->toDateString() : null,
        ];
    }
}
