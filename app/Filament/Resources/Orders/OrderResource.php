<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Orders, read-only in v2b.1; status transitions arrive with v2b.2 (docs/02_V2B_ADMIN.md §4.3). */
final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.sales');
    }

    public static function getModelLabel(): string
    {
        return __('admin.order.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.order.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', 'requested')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.order.to_process');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
