<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Products by ordered pieces in the last 12 months (order_items of non-cancelled orders). */
final class TopProductsWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.reports.top_products'))
            ->query(fn (): Builder => OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', '!=', 'cancelled')
                ->where('orders.created_at', '>=', now()->subMonths(12))
                ->select('order_items.product_id', DB::raw('MAX(order_items.product_sku) as product_sku'), DB::raw('MAX(order_items.product_name) as product_name'), DB::raw('SUM(order_items.quantity) as pieces'), DB::raw('COUNT(DISTINCT order_items.order_id) as orders'))
                ->groupBy('order_items.product_id')
                ->orderByDesc('pieces'))
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('product_sku')->label(__('admin.catalog.sku')),
                TextColumn::make('product_name')->label(__('admin.catalog.product')),
                TextColumn::make('pieces')->label(__('admin.reports.quantity')),
                TextColumn::make('orders')->label(__('admin.reports.orders')),
            ]);
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        return (string) (is_array($record) ? ($record['product_id'] ?? '') : $record->getAttribute('product_id'));
    }
}
