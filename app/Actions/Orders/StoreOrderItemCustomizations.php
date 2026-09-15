<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Support\Customizations\PricedLine;

/**
 * The snapshot of what was sold (docs/03_CUSTOMIZATIONS.md §4.5): one
 * order_item_customizations row per option chosen on the line, with the
 * option's total across the line's articles, and typed order_item_extras
 * rows for start, setup and the under-minimum surcharge. Used by the
 * checkout and by the demo seeder.
 */
final class StoreOrderItemCustomizations
{
    public function handle(OrderItem $orderItem, PricedLine $line): void
    {
        foreach ($line->customizations as $customization) {
            $option = $customization->option;
            $price = 0.0;
            $packaging = 0.0;
            foreach ($line->articles as $article) {
                foreach ($article->customizations as $articleCustomization) {
                    if ($articleCustomization->chosen->id === $option->id) {
                        $price += $articleCustomization->price;
                        $packaging += $articleCustomization->packagingPrice;
                    }
                }
            }
            $row = $orderItem->customizations()->create([
                'option_id' => $option->id,
                'family' => $customization->printing->family,
                'technique_label' => $customization->printing->technique_label,
                'position_label' => $customization->printing->position_label,
                'area_label' => $option->area->label,
                'option_label' => $option->label(),
                'number_of_colors' => $option->number_of_colors,
                'quantity' => $line->quantity,
                'price' => round($price, 2),
                'packaging_price' => $line->packaging ? round($packaging, 2) : null,
                'label' => $customization->label,
            ]);
            if ($customization->startCost > 0) {
                $orderItem->extras()->create(['type' => OrderItemExtra::TYPE_START, 'customization_id' => $row->id, 'label' => $option->startLabel(), 'price' => $customization->startCost]);
            }
            $orderItem->extras()->create(['type' => OrderItemExtra::TYPE_SETUP, 'customization_id' => $row->id, 'label' => $option->setupLabel(), 'price' => $customization->setupPrice]);
        }
        if ($line->underMinimum()) {
            $orderItem->extras()->create(['type' => OrderItemExtra::TYPE_SURCHARGE, 'label' => __('frontend.customization.under_minimum', ['minimum' => $line->minimumQuantity]), 'price' => $line->surcharge]);
        }
    }
}
