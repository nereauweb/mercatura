<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The four counters the legacy dashboard never filled in. */
final class SalesOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeProducts = Product::query()->where('active', 1)->count();
        $totalProducts = Product::query()->count();

        return [
            Stat::make(__('admin.dashboard.orders_to_process'), Order::query()->where('status', 'requested')->count())
                ->description(__('admin.dashboard.orders_to_process_hint'))
                ->color('warning'),
            Stat::make(__('admin.dashboard.unread_quotations'), Quotation::query()->whereNull('read_at')->count())
                ->color('info'),
            Stat::make(__('admin.dashboard.unread_messages'), Message::query()->whereNull('read_at')->count())
                ->color('info'),
            Stat::make(__('admin.dashboard.active_products'), $activeProducts)
                ->description(__('admin.dashboard.active_products_hint', ['total' => $totalProducts]))
                ->color('success'),
        ];
    }
}
