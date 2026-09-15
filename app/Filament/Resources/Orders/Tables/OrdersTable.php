<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label(__('admin.common.id'))->sortable(),
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('customer.company')->label(__('admin.customer.company'))->searchable()->placeholder('-'),
                TextColumn::make('customer.full_name')->label(__('admin.customer.full_name'))
                    ->searchable(['customers.name', 'customers.surname', 'customers.email']),
                TextColumn::make('total_taxed_price')->label(__('admin.order.total_taxed_price'))->money('EUR')->sortable(),
                TextColumn::make('status')->label(__('admin.order.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => Order::$status_names[$state] ?? $state)
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('payment_method')->label(__('admin.order.payment_method'))
                    ->formatStateUsing(fn (?string $state): string => Order::$payment_method_names[$state] ?? (string) $state),
                TextColumn::make('payment_status')->label(__('admin.order.payment_status'))->badge()
                    ->formatStateUsing(fn (string $state): string => Order::$payment_status_names[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'refunded' => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('tracking_code')->label(__('admin.order.tracking_code'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('admin.common.updated_at'))->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.order.status'))->options(Order::$status_names),
                SelectFilter::make('payment_status')->label(__('admin.order.payment_status'))->options(Order::$payment_status_names),
                SelectFilter::make('payment_method')->label(__('admin.order.payment_method'))->options(Order::$payment_method_names),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'requested', 'payment_notified' => 'warning',
            'paid', 'processing' => 'info',
            'delivering' => 'primary',
            'complete' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
}
